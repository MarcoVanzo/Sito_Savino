<?php

namespace App\Http\Controllers\Webhooks\Traits;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Mail\OrderConfirmation;
use App\Mail\RefundConfirmation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopEvent;
use App\Models\StockMovement;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

trait HandlesPaymentWebhooks
{
    /**
     * Il nome del gateway nei messaggi di log (es. 'Stripe', 'PayPal').
     *
     * Era una proprietà letta con `??`, cioè dichiarata da chi usa il trait ma
     * non dal trait stesso: un uso che si regge su una convenzione tacita e che
     * l'analisi statica non sa verificare. Chiederlo come metodo lo rende un
     * obbligo esplicito.
     */
    abstract protected function getGatewayName(): string;

    /**
     * Da dove arriva la notizia del pagamento: il webhook del gateway, oppure
     * il ritorno del cliente sul sito. Serve solo a rendere leggibili i log,
     * che altrimenti raccontano di un webhook anche quando webhook non c'è.
     */
    protected function canaleDiIncasso(): string
    {
        return 'webhook';
    }

    /**
     * Process a successful payment: update order, decrement stock, send notifications.
     */
    protected function handlePaymentCompleted(array $result): JsonResponse
    {
        $order = Order::find($result['order_id']);

        if (! $order) {
            Log::error("{$this->getGatewayName()} {$this->canaleDiIncasso()}: ordine non trovato", [
                'order_id' => $result['order_id'],
            ]);

            return response()->json(['error' => 'Ordine non trovato'], 404);
        }

        try {
            $outcome = DB::transaction(function () use ($order, $result) {
                // Lock the order row to prevent concurrent webhook processing (TOCTOU)
                $order = Order::lockForUpdate()->find($order->id);

                // Idempotenza sulla COPPIA (ordine, payment_id): il solo
                // `payment_id !== null` scartava in silenzio anche un SECONDO
                // pagamento con id diverso sullo stesso ordine (incasso doppio).
                if ($order->payment_id !== null) {
                    if ($order->payment_id === $result['payment_id']) {
                        Log::info("{$this->getGatewayName()} {$this->canaleDiIncasso()}: ordine già processato (idempotenza)", [
                            'order_id' => $order->id,
                            'payment_id' => $order->payment_id,
                        ]);

                        return 'replay';
                    }

                    Log::error("{$this->getGatewayName()} {$this->canaleDiIncasso()}: secondo pagamento su un ordine già pagato — probabile doppio incasso da rimborsare", [
                        'order_id' => $order->id,
                        'existing_payment_id' => $order->payment_id,
                        'new_payment_id' => $result['payment_id'],
                    ]);

                    $this->flagForManualReview(
                        $order,
                        'double_payment',
                        "Secondo pagamento ricevuto ({$result['payment_id']}) su un ordine già pagato con {$order->payment_id}: verificare ed eventualmente rimborsare.",
                        ['existing_payment_id' => $order->payment_id, 'new_payment_id' => $result['payment_id']]
                    );

                    return 'conflict';
                }

                // L'importo dichiarato dal gateway deve essere quello dell'ordine.
                if ($this->importoDiscorde($order, $result) === 'needs_review') {
                    return 'needs_review';
                }

                // Un'asta passata a un altro offerente non torna indietro: il
                // vincitore scaduto che paga in ritardo (sessione aperta prima
                // del termine, approvata dopo) troverebbe il pezzo di nuovo in
                // giacenza e se lo riprenderebbe, lasciando il nuovo vincitore
                // davanti a un lotto esaurito. Il pagamento si registra, l'ordine
                // resta com'è e va rimborsato a mano.
                if ($this->astaPassataAdAltri($order)) {
                    $order->payment_id = $result['payment_id'];
                    $order->paid_at = now();
                    $order->save();

                    $this->flagForManualReview(
                        $order,
                        'auction_reassigned',
                        "Pagamento {$result['payment_id']} ricevuto dopo che l'asta è passata a un altro offerente: ordine non confermato, da rimborsare.",
                        ['payment_id' => $result['payment_id'], 'auction_id' => $order->auction_id]
                    );

                    return 'needs_review';
                }

                // Se l'ordine era già stato annullato/rimborsato lo stock è stato
                // ripristinato: prima di registrarlo come pagato va ri-scaricato,
                // altrimenti si vende merce che non c'è più.
                if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
                    $previousStatus = $order->status->value;

                    if (! $this->reserveStockAgain($order)) {
                        // Stock insufficiente: il pagamento è un fatto (payment_id e
                        // paid_at vanno registrati, servono per idempotenza e per
                        // riconciliare un eventuale rimborso), ma l'ordine NON passa
                        // a Paid: resta annullato e in attesa di intervento manuale.
                        $order->payment_id = $result['payment_id'];
                        $order->paid_at = now();
                        $order->save();

                        Log::error("{$this->getGatewayName()} {$this->canaleDiIncasso()}: pagamento ricevuto su ordine {$previousStatus} ma stock insufficiente — ordine NON confermato, revisione manuale", [
                            'order_id' => $order->id,
                            'payment_id' => $result['payment_id'],
                            'previous_status' => $previousStatus,
                        ]);

                        $this->flagForManualReview(
                            $order,
                            'stock_shortage',
                            "Pagamento {$result['payment_id']} ricevuto su ordine {$previousStatus}: stock insufficiente per riscaricare la merce, ordine non confermato.",
                            ['previous_status' => $previousStatus, 'payment_id' => $result['payment_id']]
                        );

                        return 'needs_review';
                    }

                    Log::warning("{$this->getGatewayName()} {$this->canaleDiIncasso()}: pagamento ricevuto su un ordine {$previousStatus} — stock riscaricato e ordine confermato", [
                        'order_id' => $order->id,
                        'previous_status' => $previousStatus,
                    ]);
                }

                // 1. Update order payment info
                $order->payment_id = $result['payment_id'];
                $order->paid_at = now();
                $order->status = OrderStatus::Paid;
                $order->save();

                // 2. Lo stock NON va decrementato qui: è già stato riservato al
                // momento del checkout tramite gli StockMovement di tipo Sale
                // (CheckoutService::createOrder / AuctionCheckoutController::store).
                // Decrementarlo di nuovo causerebbe un doppio scarico di magazzino.

                // 3. Track purchase event for analytics
                ShopEvent::create([
                    'event_type' => 'purchase',
                    'viewable_type' => Order::class,
                    'viewable_id' => $order->id,
                    'user_id' => $order->user_id,
                    'session_id' => null,
                    'ip_address' => null,
                    'metadata' => [
                        'order_number' => $order->order_number,
                        'total' => $order->total_price,
                        'gateway' => strtolower($this->getGatewayName()),
                    ],
                ]);

                return 'processed';
            });

            if ($outcome === 'replay') {
                return response()->json(['message' => 'Already processed'], 200);
            }

            if ($outcome === 'conflict') {
                // 200: il pagamento è stato registrato per la revisione manuale,
                // non serve che il gateway ritenti.
                return response()->json(['message' => 'Duplicate payment flagged for review'], 200);
            }

            if ($outcome === 'needs_review') {
                return response()->json(['message' => 'Payment recorded, manual review required'], 200);
            }

            // Refresh the order to get updated data from the transaction
            $order->refresh();

            // 4. Send order confirmation email (queued)
            $this->sendOrderConfirmationEmail($order);

            // 5. Notify admin panel
            app(AdminNotificationService::class)->notifyPaymentReceived($order);

            Log::info("{$this->getGatewayName()} {$this->canaleDiIncasso()}: pagamento completato", [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
            ]);

            return response()->json(['message' => 'Pagamento processato'], 200);

        } catch (\Throwable $e) {
            // `report()` oltre al log: un pagamento incassato dal gateway ma non
            // registrato qui è denaro preso senza ordine confermato. Il solo
            // Log::error finiva su stderr, effimero e non presidiato.
            report($e);

            Log::error("{$this->getGatewayName()} {$this->canaleDiIncasso()}: errore processamento pagamento", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Errore interno'], 500);
        }
    }

    /**
     * Confronta l'incasso col totale dell'ordine e decide cosa farne.
     *
     * Fra l'apertura della sessione di pagamento e l'incasso il totale può
     * cambiare — il pannello aggiunge una riga, si riprova un pagamento su un
     * ordine ritoccato — e il cliente paga comunque la cifra della sessione
     * vecchia: senza questo controllo l'ordine risultava pagato per intero con
     * in cassa meno soldi, e nessuno se ne accorgeva.
     *
     * Incassato meno del dovuto: il pagamento è un fatto e va registrato, ma
     * l'ordine non si conferma da solo ('needs_review'). Incassato di più:
     * l'ordine si conferma e la differenza resta segnalata.
     *
     * @param  array<string, mixed>  $result
     */
    private function importoDiscorde(Order $order, array $result): ?string
    {
        $scarto = $this->scartoSulTotale($order, $result);

        if ($scarto === null) {
            return null;
        }

        $incassato = $result['amount'];
        $totale = number_format((float) $order->total_price, 2);

        if ($scarto < 0) {
            $order->payment_id = $result['payment_id'];
            $order->paid_at = now();
            $order->save();

            Log::error("{$this->getGatewayName()} {$this->canaleDiIncasso()}: incassato meno del totale dell'ordine — ordine NON confermato, revisione manuale", [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
                'incassato' => $incassato,
                'totale' => (float) $order->total_price,
            ]);

            $this->flagForManualReview(
                $order,
                'amount_mismatch',
                "Incassati {$incassato} € su un totale di {$totale} €: verificare prima di confermare l'ordine.",
                ['incassato' => $incassato, 'totale' => (float) $order->total_price]
            );

            return 'needs_review';
        }

        Log::warning("{$this->getGatewayName()} {$this->canaleDiIncasso()}: incassato più del totale dell'ordine", [
            'order_id' => $order->id,
            'incassato' => $incassato,
            'totale' => (float) $order->total_price,
        ]);

        $this->flagForManualReview(
            $order,
            'overpaid',
            "Incassati {$incassato} € su un totale di {$totale} €: differenza da rimborsare.",
            ['incassato' => $incassato, 'totale' => (float) $order->total_price]
        );

        return null;
    }

    /**
     * Di quanto l'incasso si discosta dal totale dell'ordine, in euro.
     *
     * Null quando il gateway non dichiara l'importo (o quando coincide a meno
     * del centesimo, che è la precisione con cui si fanno i conti qui).
     *
     * @param  array<string, mixed>  $result
     */
    private function scartoSulTotale(Order $order, array $result): ?float
    {
        if (! isset($result['amount']) || ! is_numeric($result['amount'])) {
            return null;
        }

        $scarto = round((float) $result['amount'] - (float) $order->total_price, 2);

        return abs($scarto) < 0.01 ? null : $scarto;
    }

    /**
     * Handle refund webhook event.
     */
    protected function handleRefund(array $result): JsonResponse
    {
        $order = Order::where('payment_id', $result['payment_id'])->first();

        if (! $order) {
            Log::warning("{$this->getGatewayName()} {$this->canaleDiIncasso()}: ordine non trovato per rimborso", [
                'payment_id' => $result['payment_id'],
            ]);

            return response()->json(['message' => 'Ordine non trovato'], 404);
        }

        try {
            $alreadyRefunded = DB::transaction(function () use ($order) {
                // Lock the order row to prevent concurrent webhook processing
                $order = Order::lockForUpdate()->find($order->id);

                // Idempotency check: if already refunded, skip processing
                if ($order->status === OrderStatus::Refunded) {
                    Log::info("{$this->getGatewayName()} {$this->canaleDiIncasso()}: rimborso già processato (idempotenza)", [
                        'order_id' => $order->id,
                    ]);

                    return true;
                }

                $order->status = OrderStatus::Refunded;
                $order->save();

                // OrderObserver::restoreStock salta il ripristino se esiste già un
                // Adjustment sull'ordine: capita quando l'ordine era stato annullato
                // (stock ripristinato), poi pagato in ritardo (stock riscaricato) e
                // infine rimborsato. Qui si riporta comunque il saldo dei movimenti
                // a zero; se l'observer ha già fatto il suo lavoro non c'è nulla da fare.
                $this->reconcileStockAfterRefund($order);

                return false;
            });

            if ($alreadyRefunded) {
                return response()->json(['message' => 'Already refunded'], 200);
            }

            // Refresh the order to get updated data from the transaction
            $order->refresh();

            // Send refund confirmation email (outside transaction)
            $recipientEmail = $order->user->email ?? $order->guest_email;
            if ($recipientEmail) {
                try {
                    Mail::to($recipientEmail)->queue(new RefundConfirmation($order));
                } catch (\Throwable $e) {
                    report($e);

                    Log::error('Errore invio email rimborso', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            }

            Log::info("{$this->getGatewayName()} {$this->canaleDiIncasso()}: rimborso registrato", [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
            ]);

            return response()->json(['message' => 'Rimborso processato'], 200);

        } catch (\Throwable $e) {
            report($e);

            Log::error("{$this->getGatewayName()} {$this->canaleDiIncasso()}: errore processamento rimborso", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Errore interno'], 500);
        }
    }

    /**
     * Movimenti di magazzino dell'ordine aggregati per prodotto/variante.
     *
     * - `sold`: quantità complessivamente venduta (movimenti Sale, in positivo)
     * - `net` : saldo di TUTTI i movimenti. net < 0 → merce ancora scaricata,
     *           net == 0 → merce già ripristinata.
     * - `missing`: quantità ancora da scaricare per onorare l'ordine (sold + net)
     *
     * @return array<string, array{product_id: int, product_variant_id: int|null, sold: int, net: int, missing: int}>
     */
    private function stockBalanceFor(Order $order): array
    {
        $movements = StockMovement::where('order_id', $order->id)
            ->lockForUpdate()
            ->get();

        $balance = [];

        foreach ($movements as $movement) {
            $key = $movement->product_id.':'.($movement->product_variant_id ?? '');

            if (! isset($balance[$key])) {
                $balance[$key] = [
                    'product_id' => (int) $movement->product_id,
                    'product_variant_id' => $movement->product_variant_id ? (int) $movement->product_variant_id : null,
                    'sold' => 0,
                    'net' => 0,
                    'missing' => 0,
                ];
            }

            $quantity = (int) $movement->quantity;
            $balance[$key]['net'] += $quantity;

            if ($movement->type === StockMovementType::Sale) {
                $balance[$key]['sold'] += abs($quantity);
            }
        }

        foreach ($balance as $key => $row) {
            $balance[$key]['missing'] = $row['sold'] + $row['net'];
        }

        return $balance;
    }

    /**
     * Ricrea i movimenti di scarico per un ordine il cui stock era già stato
     * ripristinato (annullamento/rimborso) e che risulta poi pagato.
     *
     * @return bool false se lo stock disponibile non basta (nessun movimento creato)
     */
    private function reserveStockAgain(Order $order): bool
    {
        $toDeduct = array_filter(
            $this->stockBalanceFor($order),
            fn (array $row) => $row['missing'] > 0
        );

        if (empty($toDeduct)) {
            // Stock mai ripristinato (o già riscaricato): nulla da fare.
            return true;
        }

        // Fabbisogno aggregato per prodotto: le varianti scalano anche il padre.
        $productNeed = [];
        foreach ($toDeduct as $row) {
            $productNeed[$row['product_id']] = ($productNeed[$row['product_id']] ?? 0) + $row['missing'];
        }

        // Verifica la disponibilità PRIMA di creare i movimenti: lo
        // StockMovementObserver lancerebbe un'eccezione a scarico già inserito.
        foreach ($productNeed as $productId => $need) {
            $stock = (int) (Product::lockForUpdate()->find($productId)->stock ?? 0);

            if ($stock < $need) {
                return false;
            }
        }

        foreach ($toDeduct as $row) {
            if ($row['product_variant_id'] === null) {
                continue;
            }

            $variantStock = (int) (ProductVariant::lockForUpdate()->find($row['product_variant_id'])->stock ?? 0);

            if ($variantStock < $row['missing']) {
                return false;
            }
        }

        foreach ($toDeduct as $row) {
            StockMovement::create([
                'product_id' => $row['product_id'],
                'product_variant_id' => $row['product_variant_id'],
                'order_id' => $order->id,
                'quantity' => -$row['missing'],
                'type' => StockMovementType::Sale,
                'notes' => "Ordine #{$order->id} — riscarico dopo pagamento su ordine annullato",
            ]);
        }

        return true;
    }

    /**
     * Riporta a zero il saldo dei movimenti dell'ordine dopo un rimborso,
     * coprendo i casi in cui OrderObserver::restoreStock si è auto-escluso.
     */
    private function reconcileStockAfterRefund(Order $order): void
    {
        $outstanding = array_filter(
            $this->stockBalanceFor($order),
            fn (array $row) => $row['net'] < 0
        );

        foreach ($outstanding as $row) {
            StockMovement::create([
                'product_id' => $row['product_id'],
                'product_variant_id' => $row['product_variant_id'],
                'order_id' => $order->id,
                'quantity' => abs($row['net']),
                'type' => StockMovementType::Adjustment,
                'notes' => "Ripristino Ordine #{$order->id} — rimborso",
            ]);
        }

        if (! empty($outstanding)) {
            Log::info("{$this->getGatewayName()} {$this->canaleDiIncasso()}: stock riconciliato dopo rimborso", [
                'order_id' => $order->id,
            ]);
        }
    }

    /**
     * L'ordine è di un'asta che nel frattempo ha un altro vincitore.
     */
    private function astaPassataAdAltri(Order $order): bool
    {
        if ($order->auction_id === null) {
            return false;
        }

        $vincitore = $order->auction()->value('winner_user_id');

        return $vincitore === null || (int) $vincitore !== (int) $order->user_id;
    }

    /**
     * Segnala un ordine per revisione manuale: traccia un evento shop e annota
     * il motivo sull'ordine, così è visibile anche dal pannello.
     */
    private function flagForManualReview(Order $order, string $reason, string $message, array $context = []): void
    {
        ShopEvent::create([
            'event_type' => 'payment_review',
            'viewable_type' => Order::class,
            'viewable_id' => $order->id,
            'user_id' => $order->user_id,
            'session_id' => null,
            'ip_address' => null,
            'metadata' => array_merge([
                'reason' => $reason,
                'gateway' => strtolower($this->getGatewayName()),
                'order_number' => $order->order_number,
            ], $context),
        ]);

        $note = '['.now()->format('d/m/Y H:i').' REVISIONE MANUALE] '.$message;

        $order->forceFill([
            'notes' => trim(($order->notes ? $order->notes."\n" : '').$note),
        ])->save();

        // La riga in shop_events e la nota sull'ordine restano il registro del
        // caso, ma da sole non avvisano nessuno: un doppio incasso da rimborsare
        // aspettava che qualcuno andasse a cercarlo.
        app(AdminNotificationService::class)->notifyPaymentNeedsReview($order, $reason, $message);
    }

    /**
     * Send order confirmation email to the customer.
     */
    protected function sendOrderConfirmationEmail(Order $order): void
    {
        $recipientEmail = $order->user->email ?? $order->guest_email;
        $recipientName = $order->user->name ?? $order->guest_name;

        if (! $recipientEmail) {
            Log::warning("{$this->getGatewayName()} {$this->canaleDiIncasso()}: nessuna email per conferma ordine", [
                'order_id' => $order->id,
            ]);

            return;
        }

        try {
            Mail::to($recipientEmail, $recipientName)
                ->queue(new OrderConfirmation($order));
        } catch (\Throwable $e) {
            // Il webhook non deve fallire per un problema di posta: il pagamento
            // è già registrato e il gateway ritenterebbe inutilmente. Ma va
            // segnalato, altrimenti un cliente che non riceve la conferma
            // d'ordine resta un caso invisibile.
            report($e);

            Log::error('Errore invio email conferma ordine', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

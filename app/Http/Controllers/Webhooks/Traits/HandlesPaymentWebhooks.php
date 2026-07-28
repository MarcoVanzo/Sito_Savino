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
     * Get the gateway name used in log messages (e.g. 'Stripe', 'PayPal').
     * Override in the class using this trait.
     */
    protected function getGatewayName(): string
    {
        return $this->gatewayName ?? 'Payment';
    }

    /**
     * Process a successful payment: update order, decrement stock, send notifications.
     */
    protected function handlePaymentCompleted(array $result): JsonResponse
    {
        $order = Order::find($result['order_id']);

        if (! $order) {
            Log::error("{$this->getGatewayName()} webhook: ordine non trovato", [
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
                        Log::info("{$this->getGatewayName()} webhook: ordine già processato (idempotenza)", [
                            'order_id' => $order->id,
                            'payment_id' => $order->payment_id,
                        ]);

                        return 'replay';
                    }

                    Log::error("{$this->getGatewayName()} webhook: secondo pagamento su un ordine già pagato — probabile doppio incasso da rimborsare", [
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

                        Log::error("{$this->getGatewayName()} webhook: pagamento ricevuto su ordine {$previousStatus} ma stock insufficiente — ordine NON confermato, revisione manuale", [
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

                    Log::warning("{$this->getGatewayName()} webhook: pagamento ricevuto su un ordine {$previousStatus} — stock riscaricato e ordine confermato", [
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

            Log::info("{$this->getGatewayName()} webhook: pagamento completato", [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
            ]);

            return response()->json(['message' => 'Pagamento processato'], 200);

        } catch (\Throwable $e) {
            Log::error("{$this->getGatewayName()} webhook: errore processamento pagamento", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Errore interno'], 500);
        }
    }

    /**
     * Handle refund webhook event.
     */
    protected function handleRefund(array $result): JsonResponse
    {
        $order = Order::where('payment_id', $result['payment_id'])->first();

        if (! $order) {
            Log::warning("{$this->getGatewayName()} webhook: ordine non trovato per rimborso", [
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
                    Log::info("{$this->getGatewayName()} webhook: rimborso già processato (idempotenza)", [
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
            $recipientEmail = $order->user?->email ?? $order->guest_email;
            if ($recipientEmail) {
                try {
                    Mail::to($recipientEmail)->queue(new RefundConfirmation($order));
                } catch (\Throwable $e) {
                    Log::error('Errore invio email rimborso', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            }

            Log::info("{$this->getGatewayName()} webhook: rimborso registrato", [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
            ]);

            return response()->json(['message' => 'Rimborso processato'], 200);

        } catch (\Throwable $e) {
            Log::error("{$this->getGatewayName()} webhook: errore processamento rimborso", [
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
            $stock = (int) (Product::lockForUpdate()->find($productId)?->stock ?? 0);

            if ($stock < $need) {
                return false;
            }
        }

        foreach ($toDeduct as $row) {
            if ($row['product_variant_id'] === null) {
                continue;
            }

            $variantStock = (int) (ProductVariant::lockForUpdate()->find($row['product_variant_id'])?->stock ?? 0);

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
            Log::info("{$this->getGatewayName()} webhook: stock riconciliato dopo rimborso", [
                'order_id' => $order->id,
            ]);
        }
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
    }

    /**
     * Send order confirmation email to the customer.
     */
    protected function sendOrderConfirmationEmail(Order $order): void
    {
        $recipientEmail = $order->user?->email ?? $order->guest_email;
        $recipientName = $order->user?->name ?? $order->guest_name;

        if (! $recipientEmail) {
            Log::warning("{$this->getGatewayName()} webhook: nessuna email per conferma ordine", [
                'order_id' => $order->id,
            ]);

            return;
        }

        try {
            Mail::to($recipientEmail, $recipientName)
                ->queue(new OrderConfirmation($order));
        } catch (\Throwable $e) {
            // Don't fail the webhook for email errors — log and continue
            Log::error('Errore invio email conferma ordine', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

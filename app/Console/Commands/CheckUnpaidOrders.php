<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Mail\OrderCancelled;
use App\Mail\OrderPaymentReminder;
use App\Models\Order;
use App\Services\AvvisoTecnico;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckUnpaidOrders extends Command
{
    protected $signature = 'order:check-unpaid';

    protected $description = 'Cancella ordini abbandonati Stripe/PayPal (1h) e Bonifico (shop.bank_transfer_expiry_days)';

    public function handle(): int
    {
        $reminded = 0;
        $cancelled = 0;
        // Lo stesso numero di giorni scritto al cliente nell'email di
        // conferma: prima qui erano sette cablati, l'email ne diceva cinque.
        $giorni = PaymentGateway::giorniPerIlBonifico();

        // 1. Reminder: ordini pending via bonifico negli ultimi due giorni
        // utili (l'ultimo, se il termine e' di un giorno solo)
        $ordersToRemind = Order::where('status', OrderStatus::Pending)
            ->where('payment_gateway', PaymentGateway::BankTransfer)
            ->where('created_at', '<=', now()->subDays(max(1, $giorni - 2)))
            ->where('created_at', '>', now()->subDays($giorni))
            ->get();

        foreach ($ordersToRemind as $order) {
            // Il comando gira ogni dieci minuti e la finestra dura due giorni:
            // senza un segno di "gia' inviato" lo stesso cliente riceveva
            // circa 288 promemoria. Il segno sta nello store `persistente`
            // perche' `start.sh` svuota la cache predefinita a ogni rilascio
            // (§24), e `add()` e' atomico: due giri sovrapposti non mandano
            // due email.
            if (! AvvisoTecnico::memoria()->add('promemoria-bonifico:'.$order->id, now()->toIso8601String(), now()->addDays(8))) {
                continue;
            }

            if ($this->sendReminder($order)) {
                $reminded++;
            } else {
                // Invio non riuscito: il prossimo giro ci riprova.
                AvvisoTecnico::memoria()->forget('promemoria-bonifico:'.$order->id);
            }
        }

        // 2. Auto-cancel: ordini pending via bonifico (scaduto il termine) o
        // digitali abbandonati (1h)
        $ordersToCancel = Order::where('status', OrderStatus::Pending)
            // Un ordine con una transazione registrata NON e' un checkout
            // abbandonato: il denaro e' stato incassato e l'ordine e' rimasto in
            // attesa apposta (importo diverso dal totale, revisione manuale).
            // Annullarlo qui rimetterebbe la merce a scaffale lasciando i soldi
            // del cliente senza ordine.
            ->whereNull('payment_id')
            ->where(function ($query) use ($giorni) {
                // Bonifico: cancella allo scadere del termine. Quelli d'asta
                // no: il loro termine e' quello del vincitore, e a chiuderlo
                // (annullando l'ordine e passando il lotto al successivo) e'
                // AuctionService::checkWinnerPayments().
                $query->where(function ($q) use ($giorni) {
                    $q->where('payment_gateway', PaymentGateway::BankTransfer)
                        ->where('created_at', '<=', now()->subDays($giorni))
                        ->whereNull('auction_id');
                })->orWhere(function ($q) {
                    // Stripe/PayPal: cancella dopo 1 ora (checkout abbandonato).
                    // Gli ordini d'asta sono esclusi: hanno una finestra di
                    // pagamento propria (auctions.payment_deadline_hours, 48h di
                    // default) e la loro scadenza è gestita da
                    // AuctionService::checkWinnerPayments(). Annullarli dopo
                    // un'ora lasciava il vincitore senza modo di pagare, perché
                    // AuctionCheckoutController::show lo rimanda alla pagina
                    // "ordine già effettuato".
                    $q->whereIn('payment_gateway', [PaymentGateway::Stripe, PaymentGateway::PayPal])
                        ->where('created_at', '<=', now()->subHours(1))
                        // Un pagamento gia' partito ma a esito differito
                        // (SEPA su Stripe, cattura PayPal in verifica) non e'
                        // un checkout abbandonato: lo chiudono i webhook,
                        // che confermano o annullano.
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('shop_events')
                                ->whereColumn('shop_events.viewable_id', 'orders.id')
                                ->where('shop_events.viewable_type', Order::class)
                                ->where('shop_events.event_type', 'payment_review')
                                ->where('shop_events.metadata->reason', 'payment_pending');
                        })
                        ->whereNotExists(function ($sub) {
                            // Solo le aste vive proteggono il loro ordine: senza il
                            // filtro su deleted_at un'asta soft-deleted teneva in vita
                            // per sempre un ordine Pending, bloccandone lo stock.
                            $sub->select(DB::raw(1))
                                ->from('auctions')
                                ->whereColumn('auctions.id', 'orders.auction_id')
                                ->whereNull('auctions.deleted_at');
                        });
                });
            })
            ->get();

        foreach ($ordersToCancel as $order) {
            if ($this->cancelOrder($order)) {
                $cancelled++;
            }
        }

        // 3. Log & output
        $this->info("Promemoria inviati: {$reminded}");
        $this->info("Ordini cancellati: {$cancelled}");

        Log::info('CheckUnpaidOrders completato', [
            'reminded' => $reminded,
            'cancelled' => $cancelled,
        ]);

        return self::SUCCESS;
    }

    /**
     * Send a payment reminder email to the customer.
     *
     * Falso solo quando conviene riprovare al giro dopo: un ordine senza
     * indirizzo non ne avra' uno fra dieci minuti.
     */
    private function sendReminder(Order $order): bool
    {
        $recipientEmail = $order->user->email ?? $order->guest_email;
        $recipientName = $order->user->name ?? $order->guest_name;

        if (! $recipientEmail) {
            Log::warning('CheckUnpaidOrders: nessuna email per promemoria', [
                'order_id' => $order->id,
            ]);

            return true;
        }

        try {
            Mail::to($recipientEmail, $recipientName)
                ->queue(new OrderPaymentReminder($order));

            Log::info('Promemoria pagamento inviato', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Errore invio promemoria pagamento', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cancel an unpaid order: update status and send notification.
     * Note: Stock restoration is automatically handled by OrderObserver.
     *
     * L'ordine si rilegge sotto lock e si ricontrolla: fra la query iniziale
     * e questo punto puo' essere arrivato il pagamento (webhook, ritorno dal
     * gateway). Scrivere lo stato sulla copia letta all'inizio annullava un
     * ordine appena pagato e rimetteva a scaffale merce venduta.
     *
     * @return bool true se l'ordine e' stato annullato adesso
     */
    private function cancelOrder(Order $order): bool
    {
        try {
            $annullato = DB::transaction(function () use ($order) {
                $attuale = Order::lockForUpdate()->find($order->id);

                if ($attuale === null
                    || $attuale->status !== OrderStatus::Pending
                    || $attuale->payment_id !== null) {
                    return null;
                }

                $attuale->status = OrderStatus::Cancelled;
                $attuale->save();

                return $attuale;
            });

            if ($annullato === null) {
                Log::info('CheckUnpaidOrders: ordine cambiato nel frattempo, annullamento saltato', [
                    'order_id' => $order->id,
                ]);

                return false;
            }

            // Send cancellation email
            $this->sendCancellationEmail($annullato);

            Log::info('Ordine cancellato per mancato pagamento o abbandono', [
                'order_id' => $annullato->id,
                'order_number' => $annullato->order_number,
                'gateway' => $annullato->payment_gateway->value ?? $annullato->payment_gateway,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Errore cancellazione ordine non pagato', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a cancellation notification email.
     */
    private function sendCancellationEmail(Order $order): void
    {
        $recipientEmail = $order->user->email ?? $order->guest_email;
        $recipientName = $order->user->name ?? $order->guest_name;

        if (! $recipientEmail) {
            return;
        }

        try {
            Mail::to($recipientEmail, $recipientName)
                ->queue(new OrderCancelled($order));
        } catch (\Throwable $e) {
            Log::error('Errore invio email cancellazione ordine', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

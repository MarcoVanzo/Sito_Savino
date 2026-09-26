<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Mail\OrderCancelled;
use App\Mail\OrderPaymentReminder;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckUnpaidOrders extends Command
{
    protected $signature = 'order:check-unpaid';

    protected $description = 'Cancella ordini abbandonati Stripe/PayPal (1h) e Bonifico (7gg)';

    public function handle(): int
    {
        $reminded = 0;
        $cancelled = 0;

        // 1. Reminder: ordini pending via bonifico creati tra 5 e 7 giorni fa
        $ordersToRemind = Order::where('status', OrderStatus::Pending)
            ->where('payment_gateway', PaymentGateway::BankTransfer)
            ->where('created_at', '<=', now()->subDays(5))
            ->where('created_at', '>', now()->subDays(7))
            ->get();

        foreach ($ordersToRemind as $order) {
            $this->sendReminder($order);
            $reminded++;
        }

        // 2. Auto-cancel: ordini pending via bonifico (7gg) o digitali abbandonati (1h)
        $ordersToCancel = Order::where('status', OrderStatus::Pending)
            // Un ordine con una transazione registrata NON e' un checkout
            // abbandonato: il denaro e' stato incassato e l'ordine e' rimasto in
            // attesa apposta (importo diverso dal totale, revisione manuale).
            // Annullarlo qui rimetterebbe la merce a scaffale lasciando i soldi
            // del cliente senza ordine.
            ->whereNull('payment_id')
            ->where(function ($query) {
                // Bonifico: cancella dopo 7 giorni
                $query->where(function ($q) {
                    $q->where('payment_gateway', PaymentGateway::BankTransfer)
                        ->where('created_at', '<=', now()->subDays(7));
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
     */
    private function sendReminder(Order $order): void
    {
        $recipientEmail = $order->user->email ?? $order->guest_email;
        $recipientName = $order->user->name ?? $order->guest_name;

        if (! $recipientEmail) {
            Log::warning('CheckUnpaidOrders: nessuna email per promemoria', [
                'order_id' => $order->id,
            ]);

            return;
        }

        try {
            Mail::to($recipientEmail, $recipientName)
                ->queue(new OrderPaymentReminder($order));

            Log::info('Promemoria pagamento inviato', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        } catch (\Throwable $e) {
            Log::error('Errore invio promemoria pagamento', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
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

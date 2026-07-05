<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
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
            ->where(function ($query) {
                // Bonifico: cancella dopo 7 giorni
                $query->where(function ($q) {
                    $q->where('payment_gateway', PaymentGateway::BankTransfer)
                      ->where('created_at', '<=', now()->subDays(7));
                })->orWhere(function ($q) {
                    // Stripe/PayPal: cancella dopo 1 ora (checkout abbandonato)
                    $q->whereIn('payment_gateway', [PaymentGateway::Stripe, PaymentGateway::PayPal])
                      ->where('created_at', '<=', now()->subHours(1));
                });
            })
            ->get();

        foreach ($ordersToCancel as $order) {
            $this->cancelOrder($order);
            $cancelled++;
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
        $recipientEmail = $order->user?->email ?? $order->guest_email;
        $recipientName = $order->user?->name ?? $order->guest_name;

        if (! $recipientEmail) {
            Log::warning('CheckUnpaidOrders: nessuna email per promemoria', [
                'order_id' => $order->id,
            ]);

            return;
        }

        try {
            Mail::to($recipientEmail, $recipientName)
                ->queue(new \App\Mail\OrderPaymentReminder($order));

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
     */
    private function cancelOrder(Order $order): void
    {
        try {
            DB::transaction(function () use ($order) {
                // Update status to cancelled
                $order->status = OrderStatus::Cancelled;
                $order->save();
            });

            // Send cancellation email
            $this->sendCancellationEmail($order);

            Log::info('Ordine cancellato per mancato pagamento o abbandono', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'gateway' => $order->payment_gateway->value ?? $order->payment_gateway,
            ]);
        } catch (\Throwable $e) {
            Log::error('Errore cancellazione ordine non pagato', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a cancellation notification email.
     */
    private function sendCancellationEmail(Order $order): void
    {
        $recipientEmail = $order->user?->email ?? $order->guest_email;
        $recipientName = $order->user?->name ?? $order->guest_name;

        if (! $recipientEmail) {
            return;
        }

        try {
            Mail::to($recipientEmail, $recipientName)
                ->queue(new \App\Mail\OrderCancelled($order));
        } catch (\Throwable $e) {
            Log::error('Errore invio email cancellazione ordine', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

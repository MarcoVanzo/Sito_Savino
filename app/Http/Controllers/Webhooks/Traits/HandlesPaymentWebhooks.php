<?php

namespace App\Http\Controllers\Webhooks\Traits;

use App\Enums\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Mail\RefundConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopEvent;
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
            $alreadyProcessed = DB::transaction(function () use ($order, $result) {
                // Lock the order row to prevent concurrent webhook processing (TOCTOU)
                $order = Order::lockForUpdate()->find($order->id);

                // Idempotency check: if already paid, skip processing
                if ($order->payment_id !== null) {
                    Log::info("{$this->getGatewayName()} webhook: ordine già processato (idempotenza)", [
                        'order_id' => $order->id,
                        'payment_id' => $order->payment_id,
                    ]);

                    return true; // Already processed
                }

                // 1. Update order payment info
                $order->payment_id = $result['payment_id'];
                $order->paid_at = now();
                $order->status = OrderStatus::Paid;
                $order->save();

                // 2. Decrement stock atomically for each order item
                $order->load('items');
                foreach ($order->items as $item) {
                    /** @var OrderItem $item */
                    if ($item->product_variant_id) {
                        ProductVariant::where('id', $item->product_variant_id)
                            ->decrement('stock', $item->quantity);
                    } else {
                        Product::where('id', $item->product_id)
                            ->decrement('stock', $item->quantity);
                    }
                }

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

                return false;
            });

            if ($alreadyProcessed) {
                return response()->json(['message' => 'Already processed'], 200);
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

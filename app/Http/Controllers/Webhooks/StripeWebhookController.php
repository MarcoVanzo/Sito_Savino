<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ShopEvent;
use App\Services\AdminNotificationService;
use App\Services\Payments\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController
{
    /**
     * Handle incoming Stripe webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $service = new StripePaymentService;
            $result = $service->handleWebhook(
                $request->getContent(),
                ['stripe-signature' => $request->header('Stripe-Signature', '')],
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: firma non valida', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Firma non valida'], 400);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: errore verifica', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Errore di verifica'], 400);
        }

        // Ignore non-actionable events
        if ($result['status'] === 'ignored') {
            return response()->json(['message' => 'Evento ignorato'], 200);
        }

        // Handle completed payment
        if ($result['status'] === 'completed') {
            return $this->handlePaymentCompleted($result);
        }

        // Handle refund
        if ($result['status'] === 'refunded') {
            return $this->handleRefund($result);
        }

        return response()->json(['message' => 'OK'], 200);
    }

    /**
     * Process a successful payment: update order, decrement stock, send notifications.
     */
    private function handlePaymentCompleted(array $result): JsonResponse
    {
        $order = Order::find($result['order_id']);

        if (! $order) {
            Log::error('Stripe webhook: ordine non trovato', [
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
                    Log::info('Stripe webhook: ordine già processato (idempotenza)', [
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

                // 2. Track purchase event for analytics
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
                        'gateway' => 'stripe',
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

            Log::info('Stripe webhook: pagamento completato', [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
            ]);

            return response()->json(['message' => 'Pagamento processato'], 200);

        } catch (\Throwable $e) {
            Log::error('Stripe webhook: errore processamento pagamento', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Errore interno'], 500);
        }
    }

    /**
     * Handle charge.refunded webhook event.
     */
    private function handleRefund(array $result): JsonResponse
    {
        $order = Order::where('payment_id', $result['payment_id'])->first();

        if ($order && $order->status !== OrderStatus::Refunded) {
            $order->status = OrderStatus::Refunded;
            $order->save();

            Log::info('Stripe webhook: rimborso registrato', [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
            ]);
        }

        return response()->json(['message' => 'Rimborso processato'], 200);
    }


    /**
     * Send order confirmation email to the customer.
     */
    private function sendOrderConfirmationEmail(Order $order): void
    {
        $recipientEmail = $order->user?->email ?? $order->guest_email;
        $recipientName = $order->user?->name ?? $order->guest_name;

        if (! $recipientEmail) {
            Log::warning('Stripe webhook: nessuna email per conferma ordine', [
                'order_id' => $order->id,
            ]);

            return;
        }

        try {
            Mail::to($recipientEmail, $recipientName)
                ->queue(new \App\Mail\OrderConfirmation($order));
        } catch (\Throwable $e) {
            // Don't fail the webhook for email errors — log and continue
            Log::error('Errore invio email conferma ordine', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

}

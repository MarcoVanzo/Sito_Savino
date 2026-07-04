<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopEvent;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Payments\StripePaymentService;
use Filament\Notifications\Notification;
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
            DB::transaction(function () use ($order, $result) {
                // Lock the order row to prevent concurrent webhook processing (TOCTOU)
                $order = Order::lockForUpdate()->find($order->id);

                // Idempotency check: if already paid, skip processing
                if ($order->payment_id !== null) {
                    Log::info('Stripe webhook: ordine già processato (idempotenza)', [
                        'order_id' => $order->id,
                        'payment_id' => $order->payment_id,
                    ]);

                    return; // Already processed — skip within transaction
                }

                // 1. Update order payment info
                $order->update([
                    'payment_id' => $result['payment_id'],
                    'paid_at' => now(),
                    'status' => OrderStatus::Paid,
                ]);

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
            });

            // 4. Send order confirmation email (queued)
            $this->sendOrderConfirmationEmail($order);

            // 5. Notify admin panel
            $this->notifyAdmins($order);

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
            $order->update(['status' => OrderStatus::Refunded]);

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

    /**
     * Send a Filament notification to admin users about the new order.
     */
    private function notifyAdmins(Order $order): void
    {
        try {
            $admins = User::whereIn('role', [
                UserRole::SuperAdmin,
                UserRole::ShopManager,
            ])->get();

            Notification::make()
                ->title('Nuovo ordine pagato')
                ->body("Ordine {$order->order_number} — €" . number_format((float) $order->total_price, 2, ',', '.'))
                ->icon('heroicon-o-shopping-bag')
                ->success()
                ->sendToDatabase($admins);
        } catch (\Throwable $e) {
            // Don't fail the webhook for notification errors
            Log::error('Errore notifica admin nuovo ordine', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

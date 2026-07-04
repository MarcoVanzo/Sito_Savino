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
use App\Services\Payments\PayPalPaymentService;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PayPalWebhookController
{
    /**
     * Handle incoming PayPal webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $service = new PayPalPaymentService;
            $result = $service->handleWebhook(
                $request->getContent(),
                $request->headers->all(),
            );
        } catch (\RuntimeException $e) {
            Log::warning('PayPal webhook: verifica fallita', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Verifica fallita'], 400);
        } catch (\Throwable $e) {
            Log::error('PayPal webhook: errore generico', [
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
            Log::error('PayPal webhook: ordine non trovato', [
                'order_id' => $result['order_id'],
            ]);

            return response()->json(['error' => 'Ordine non trovato'], 404);
        }

        // Idempotency check: if already paid, skip processing
        if ($order->payment_id !== null) {
            Log::info('PayPal webhook: ordine già processato (idempotenza)', [
                'order_id' => $order->id,
                'payment_id' => $order->payment_id,
            ]);

            return response()->json(['message' => 'Già processato'], 200);
        }

        try {
            DB::transaction(function () use ($order, $result) {
                // 1. Update order payment info
                $order->update([
                    'payment_id' => $result['payment_id'],
                    'paid_at' => now(),
                    'status' => OrderStatus::Paid,
                ]);

                // 2. Decrement stock atomically and record movements
                $this->decrementStock($order);

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
                        'gateway' => 'paypal',
                    ],
                ]);
            });

            // 4. Send order confirmation email (queued)
            $this->sendOrderConfirmationEmail($order);

            // 5. Notify admin panel
            $this->notifyAdmins($order);

            Log::info('PayPal webhook: pagamento completato', [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
            ]);

            return response()->json(['message' => 'Pagamento processato'], 200);

        } catch (\Throwable $e) {
            Log::error('PayPal webhook: errore processamento pagamento', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Errore interno'], 500);
        }
    }

    /**
     * Handle PAYMENT.CAPTURE.REFUNDED webhook event.
     */
    private function handleRefund(array $result): JsonResponse
    {
        $order = Order::where('payment_id', $result['payment_id'])->first();

        if ($order && $order->status !== OrderStatus::Refunded) {
            $order->update(['status' => OrderStatus::Refunded]);

            Log::info('PayPal webhook: rimborso registrato', [
                'order_id' => $order->id,
                'payment_id' => $result['payment_id'],
            ]);
        }

        return response()->json(['message' => 'Rimborso processato'], 200);
    }

    /**
     * Atomically decrement stock for each order item and record stock movements.
     */
    private function decrementStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                ProductVariant::where('id', $item->product_variant_id)
                    ->decrement('stock', $item->quantity);
            } else {
                Product::where('id', $item->product_id)
                    ->decrement('stock', $item->quantity);
            }

            StockMovement::create([
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'order_id' => $order->id,
                'quantity' => -$item->quantity,
                'type' => StockMovementType::Sale,
                'notes' => "Vendita ordine {$order->order_number}",
            ]);
        }
    }

    /**
     * Send order confirmation email to the customer.
     */
    private function sendOrderConfirmationEmail(Order $order): void
    {
        $recipientEmail = $order->user?->email ?? $order->guest_email;
        $recipientName = $order->user?->name ?? $order->guest_name;

        if (! $recipientEmail) {
            Log::warning('PayPal webhook: nessuna email per conferma ordine', [
                'order_id' => $order->id,
            ]);

            return;
        }

        try {
            Mail::to($recipientEmail, $recipientName)
                ->queue(new \App\Mail\OrderConfirmation($order));
        } catch (\Throwable $e) {
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
            Log::error('Errore notifica admin nuovo ordine', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

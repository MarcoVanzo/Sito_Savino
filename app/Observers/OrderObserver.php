<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     * Invalida la cache dei widget dashboard.
     */
    public function created(Order $order): void
    {
        Cache::forget('filament:dashboard:stats');
        Cache::forget('filament:dashboard:orders_chart');
    }

    /**
     * Handle the Order "updated" event.
     *
     * Quando un ordine viene cancellato o rimborsato, ripristina lo stock.
     * Nota: il decremento stock è gestito da CheckoutService al momento del pagamento.
     */
    public function updated(Order $order): void
    {
        if (! $order->isDirty('status')) {
            return;
        }

        // Invalida la cache dei widget dashboard quando lo status cambia
        Cache::forget('filament:dashboard:stats');
        Cache::forget('filament:dashboard:orders_chart');

        // Ordine cancellato o rimborsato → ripristina stock riservato al checkout
        // Lo stock viene riservato al momento del checkout (Pending), quindi va
        // ripristinato indipendentemente dallo status precedente.
        // Il guard $alreadyRestored in restoreStock() previene la doppia esecuzione.
        if ($order->status === OrderStatus::Cancelled || $order->status === OrderStatus::Refunded) {
            $this->restoreStock($order);
        }

        // Send status change notification to customer
        // Non invia per: Pending (stato iniziale), Paid (webhook gestisce), Shipped (admin invia email con tracking separata)
        $statusesThatNotify = [OrderStatus::Processing, OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Refunded];
        if (in_array($order->status, $statusesThatNotify)) {
            $recipientEmail = $order->user?->email ?? $order->guest_email;
            if ($recipientEmail) {
                \Illuminate\Support\Facades\Mail::to($recipientEmail)
                    ->queue(new \App\Mail\OrderStatusChanged($order, $order->getOriginal('status'), $order->status->value));
            }
        }
    }

    /**
     * Ripristina lo stock per un ordine cancellato.
     */
    private function restoreStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Verifica che ci siano movimenti di vendita da ripristinare (via FK)
            $salesMovements = StockMovement::where('order_id', $order->id)
                ->where('type', StockMovementType::Sale)
                ->lockForUpdate()
                ->get();

            if ($salesMovements->isEmpty()) {
                return;
            }

            // Verifica che non sia già stato ripristinato
            $alreadyRestored = StockMovement::where('order_id', $order->id)
                ->where('type', StockMovementType::Adjustment)
                ->exists();

            if ($alreadyRestored) {
                Log::warning("Stock già ripristinato per Ordine #{$order->id} — operazione saltata");

                return;
            }

            foreach ($salesMovements as $movement) {
                // Il StockMovementObserver aggiorna automaticamente lo stock
                // quando il movimento viene creato — non fare increment manuale
                StockMovement::create([
                    'product_id' => $movement->product_id,
                    'product_variant_id' => $movement->product_variant_id,
                    'order_id' => $order->id,
                    'quantity' => abs($movement->quantity),
                    'type' => StockMovementType::Adjustment,
                    'notes' => "Ripristino Ordine #{$order->id} — {$order->status->getLabel()}",
                ]);
            }

            Log::info("Stock ripristinato per Ordine #{$order->id} (cancellazione)");
        });
    }

    /**
     * Handle the Order "deleted" event.
     * Invalida la cache dei widget dashboard.
     */
    public function deleted(Order $order): void
    {
        Cache::forget('filament:dashboard:stats');
        Cache::forget('filament:dashboard:orders_chart');
    }
}

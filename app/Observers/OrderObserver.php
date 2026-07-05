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

        // Ordine cancellato o rimborsato → ripristina stock (solo se era stato pagato)
        if ($order->status === OrderStatus::Cancelled || $order->status === OrderStatus::Refunded) {
            $originalStatus = $order->getOriginal('status');
            // getOriginal() può tornare stringa o enum in base alla versione Laravel
            $wasPaid = $originalStatus === OrderStatus::Paid
                || $originalStatus === OrderStatus::Paid->value;

            if ($wasPaid) {
                $this->restoreStock($order);
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
                StockMovement::create([
                    'product_id' => $movement->product_id,
                    'product_variant_id' => $movement->product_variant_id,
                    'order_id' => $order->id,
                    'quantity' => abs($movement->quantity),
                    'type' => StockMovementType::Adjustment,
                    'notes' => "Ripristino Ordine #{$order->id} — cancellazione",
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

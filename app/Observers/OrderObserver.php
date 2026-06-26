<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     *
     * Quando un ordine passa a "paid", decrementa lo stock atomicamente.
     * Quando un ordine viene cancellato, ripristina lo stock.
     */
    public function updated(Order $order): void
    {
        if (!$order->isDirty('status')) {
            return;
        }

        // Ordine pagato → decrementa stock
        if ($order->status === OrderStatus::Paid) {
            $this->decrementStock($order);
        }

        // Ordine cancellato → ripristina stock (solo se era stato pagato)
        if ($order->status === OrderStatus::Cancelled) {
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
     * Decrementa lo stock per un ordine pagato.
     * Usa order_id per idempotenza (non più la fragile stringa notes).
     */
    private function decrementStock(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Check idempotenza DENTRO la transaction: controlla se esistono già movimenti per quest'ordine
            $alreadyProcessed = StockMovement::where('order_id', $order->id)
                ->where('type', StockMovementType::Sale)
                ->lockForUpdate()
                ->exists();

            if ($alreadyProcessed) {
                Log::warning("Stock già decrementato per Ordine #{$order->id} — operazione saltata");
                return;
            }

            // Eager-load items CON relazioni per evitare N+1
            $order->load('items.variant', 'items.product');

            foreach ($order->items as $item) {
                // Verifica disponibilità stock prima di decrementare
                $currentStock = $this->getCurrentStock($item);

                if ($currentStock < $item->quantity) {
                    Log::error("Stock insufficiente per Ordine #{$order->id}: " .
                        "prodotto {$item->product_id}, richiesti {$item->quantity}, disponibili {$currentStock}");
                    // Procede comunque (il pagamento è avvenuto), ma logga il warning
                }

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'order_id' => $order->id,
                    'quantity' => -abs($item->quantity),
                    'type' => StockMovementType::Sale,
                    'notes' => "Ordine #{$order->id} — vendita",
                ]);
            }
        });

        Log::info("Stock decrementato per Ordine #{$order->id}");
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
        });

        Log::info("Stock ripristinato per Ordine #{$order->id} (cancellazione)");
    }

    /**
     * Recupera lo stock corrente per un item (variante o prodotto).
     */
    private function getCurrentStock($item): int
    {
        if ($item->product_variant_id) {
            return $item->variant?->stock ?? 0;
        }

        return $item->product?->stock ?? 0;
    }
}

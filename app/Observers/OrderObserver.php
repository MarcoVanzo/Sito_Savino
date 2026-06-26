<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "updated" event.
     * Quando un ordine passa a "paid", decrementa lo stock atomicamente.
     */
    public function updated(Order $order): void
    {
        if ($order->isDirty('status') && $order->status === 'paid') {
            // Controlla se lo stock è già stato decrementato per questo ordine
            // usando match esatto sulla stringa notes (non LIKE, non JSON)
            $alreadyProcessed = StockMovement::where('type', 'Vendita')
                ->where('notes', "Vendita da Ordine #{$order->id}")
                ->exists();

            if ($alreadyProcessed) {
                return;
            }

            // Transazione DB per garantire atomicità
            DB::transaction(function () use ($order) {
                foreach ($order->items as $item) {
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => -abs($item->quantity),
                        'type' => 'Vendita',
                        'notes' => "Vendita da Ordine #{$order->id}",
                    ]);
                }
            });

            Log::info("Stock decrementato per Ordine #{$order->id}");
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}

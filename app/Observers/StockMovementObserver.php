<?php

namespace App\Observers;

use App\Models\StockMovement;

class StockMovementObserver
{
    /**
     * Handle the StockMovement "created" event.
     * 
     * Aggiorna lo stock: se il movement ha una variante, aggiorna SOLO quella.
     * Se non ha variante, aggiorna il prodotto padre.
     * Questo evita il doppio conteggio.
     */
    public function created(StockMovement $stockMovement): void
    {
        if ($stockMovement->product_variant_id && $stockMovement->variant) {
            // Ha variante → aggiorna SOLO la variante
            $stockMovement->variant->increment('stock', $stockMovement->quantity);
        } elseif ($stockMovement->product_id && $stockMovement->product) {
            // Non ha variante → aggiorna il prodotto
            $stockMovement->product->increment('stock', $stockMovement->quantity);
        }
    }

    /**
     * Handle the StockMovement "updated" event.
     */
    public function updated(StockMovement $stockMovement): void
    {
        //
    }

    /**
     * Handle the StockMovement "deleted" event.
     */
    public function deleted(StockMovement $stockMovement): void
    {
        //
    }

    /**
     * Handle the StockMovement "restored" event.
     */
    public function restored(StockMovement $stockMovement): void
    {
        //
    }

    /**
     * Handle the StockMovement "force deleted" event.
     */
    public function forceDeleted(StockMovement $stockMovement): void
    {
        //
    }
}

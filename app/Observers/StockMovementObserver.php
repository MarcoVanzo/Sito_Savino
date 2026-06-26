<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockMovementObserver
{
    /**
     * Handle the StockMovement "created" event.
     *
     * Aggiorna lo stock atomicamente: se il movement ha una variante,
     * aggiorna SOLO quella. Se non ha variante, aggiorna il prodotto padre.
     * Protegge da stock negativo con UPDATE condizionale atomico.
     */
    public function created(StockMovement $stockMovement): void
    {
        DB::transaction(function () use ($stockMovement) {
            if ($stockMovement->product_variant_id) {
                $this->updateStock(
                    ProductVariant::class,
                    $stockMovement->product_variant_id,
                    $stockMovement->quantity
                );
            } elseif ($stockMovement->product_id) {
                $this->updateStock(
                    Product::class,
                    $stockMovement->product_id,
                    $stockMovement->quantity
                );
            }
        });

        Log::debug("StockMovement #{$stockMovement->id}: {$stockMovement->type->value} qty={$stockMovement->quantity} " .
            "product={$stockMovement->product_id} variant={$stockMovement->product_variant_id}");
    }

    /**
     * Il metodo deleted() è stato rimosso intenzionalmente.
     * I movimenti di magazzino sono record di audit immutabili.
     * La cancellazione è impedita a livello UI (StockMovementResource).
     * Il ripristino stock è gestito da OrderObserver::restoreStock().
     */

    /**
     * Aggiorna lo stock con guard atomico contro valori negativi.
     * Usa un UPDATE condizionale (WHERE stock >= abs(quantity)) per evitare
     * race condition TOCTOU. Se nessuna riga viene aggiornata, lo stock
     * è insufficiente.
     *
     * @param  class-string<Product|ProductVariant>  $modelClass
     * @throws \RuntimeException Se lo stock risultante sarebbe negativo.
     */
    private function updateStock(string $modelClass, int $id, int $quantity): void
    {
        if ($quantity < 0) {
            // UPDATE atomico condizionale: aggiorna SOLO se stock sufficiente
            $affected = $modelClass::where('id', $id)
                ->where('stock', '>=', abs($quantity))
                ->update(['stock' => DB::raw('stock + (' . (int) $quantity . ')')]);

            if ($affected === 0) {
                $currentStock = $modelClass::where('id', $id)->value('stock') ?? 0;
                $label = $modelClass === ProductVariant::class ? 'Variante' : 'Prodotto';
                Log::error("Stock insufficiente: {$label} #{$id} ha stock={$currentStock}, richiesto decremento di " . abs($quantity));
                throw new \RuntimeException(
                    "Stock insufficiente per {$label} #{$id}: disponibile {$currentStock}, richiesto " . abs($quantity)
                );
            }
        } else {
            $modelClass::where('id', $id)->increment('stock', $quantity);
        }
    }
}

<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Handle the Product "updated" event.
     * Se il prodotto viene disattivato, rimuove tutti i CartItem associati.
     */
    public function updated(Product $product): void
    {
        if ($product->isDirty('is_active') && ! $product->is_active) {
            $this->removeCartItems($product, 'disattivazione');
        }
    }

    /**
     * Handle the Product "deleted" event (soft delete).
     * Rimuove tutti i CartItem associati al prodotto eliminato.
     */
    public function deleted(Product $product): void
    {
        $this->removeCartItems($product, 'eliminazione');
    }

    /**
     * Rimuove tutti i CartItem associati al prodotto e logga l'operazione.
     */
    private function removeCartItems(Product $product, string $reason): void
    {
        $count = $product->cartItems()->count();

        if ($count === 0) {
            return;
        }

        $product->cartItems()->delete();

        Log::info("Rimossi {$count} articoli dal carrello per {$reason} prodotto \"{$product->getTranslation('name', 'it')}\" (ID: {$product->id})");
    }
}

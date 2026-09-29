<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\StoricoPrezzi;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Un prodotto nuovo entra in cima alla vetrina, com'era quando le
     * categorie si aprivano sui piu' recenti, e con una posizione tutta sua:
     * gli altri scendono di uno. A zero, com'era la colonna, due prodotti
     * nuovi avrebbero la stessa posizione e il riordino del pannello, che
     * scambia le posizioni occupate (ListProducts::reorderTable), non
     * saprebbe come metterli uno dopo l'altro.
     *
     * Chi passa una posizione esplicita (i test, un import) la tiene.
     */
    public function creating(Product $product): void
    {
        if (array_key_exists('sort_order', $product->getAttributes())) {
            return;
        }

        // Query builder nudo: nessun evento e nessun updated_at toccato su
        // prodotti che nessuno ha modificato.
        Product::withTrashed()->toBase()->increment('sort_order');
        $product->sort_order = 1;
    }

    /**
     * Ogni salvataggio che cambia il prezzo effettivo apre una riga dello
     * storico: è da lì che si calcola il prezzo da barrare accanto a uno
     * sconto (StoricoPrezzi). Gli sconti programmati li registra
     * `prezzi:registra`, ogni ora.
     */
    public function saved(Product $product): void
    {
        app(StoricoPrezzi::class)->registra($product);
    }

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

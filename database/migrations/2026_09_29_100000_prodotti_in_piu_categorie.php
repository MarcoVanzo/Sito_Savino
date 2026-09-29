<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un prodotto puo' stare in piu' categorie: la maglia del libero sia in Home
 * sia in Away, un capo sia in Abbigliamento sia in Outlet.
 *
 * `products.product_category_id` resta la categoria principale — quella
 * scritta sulla card, usata per gli articoli correlati e per l'anteprima
 * social — e qui stanno le altre. La vetrina cerca un prodotto in entrambe
 * (Product::scopeNelleCategorie).
 *
 * Nella stessa occasione i prodotti prendono una posizione distinta in
 * `sort_order`, finora zero per tutti: il riordino del pannello rimescola le
 * posizioni gia' occupate (ListProducts::reorderTable), e a pari merito non
 * ci sarebbe niente da scambiare. Da qui in avanti i prodotti nuovi entrano
 * in cima con una posizione propria (ProductObserver::creating).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->numeraLePosizioni();

        if (Schema::hasTable('product_category_product')) {
            return;
        }

        Schema::create('product_category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'product_category_id']);
            $table->index('product_category_id');
        });
    }

    /**
     * Posizioni 1..n nell'ordine che la vetrina mostra oggi
     * (Product::scopeInOrdineDiVetrina): un ordine gia' dato con sort_order
     * resta com'e', i pari merito — tutti, finora — si sciolgono dal piu'
     * recente.
     */
    private function numeraLePosizioni(): void
    {
        $ids = DB::table('products')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id');

        foreach ($ids as $i => $id) {
            DB::table('products')->where('id', $id)->update(['sort_order' => $i + 1]);
        }
    }

    // Le posizioni non si riportano a zero: sono gia' l'ordine di prima.
    public function down(): void
    {
        Schema::dropIfExists('product_category_product');
    }
};

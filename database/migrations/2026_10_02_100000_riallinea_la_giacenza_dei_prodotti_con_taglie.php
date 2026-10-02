<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il riepilogo `products.stock` dei prodotti con taglie era rimasto indietro
 * (a 0 per quelli importati): le taglie si scrivevano dal pannello senza
 * toccarlo, e lo StockMovementObserver lo scalava con la guardia contro il
 * negativo, facendo fallire il checkout di una taglia disponibile
 * (02/10/2026). Da qui in poi lo tiene allineato
 * Product::riallineaLaGiacenzaDelleTaglie(); questa lo porta in pari una volta.
 *
 * A guardie: tocca solo i prodotti con taglie il cui riepilogo differisce
 * dalla somma. I prodotti senza taglie restano come sono.
 */
return new class extends Migration
{
    public function up(): void
    {
        $somme = DB::table('product_variants')
            ->selectRaw('product_id, SUM(stock) AS totale')
            ->groupBy('product_id')
            ->pluck('totale', 'product_id');

        foreach ($somme as $productId => $totale) {
            DB::table('products')
                ->where('id', $productId)
                ->where('stock', '!=', (int) $totale)
                ->update(['stock' => (int) $totale]);
        }
    }

    /**
     * No-op: il valore di prima era sbagliato e non serve a nessuno.
     */
    public function down(): void {}
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ti potrebbe interessare anche": gli articoli li sceglie la redazione.
 *
 * La sezione in fondo alla scheda prodotto pescava quattro articoli a caso
 * della stessa categoria. Serve invece poter accostare la maglia di un'atleta
 * ai suoi accessori, che stanno in reparti diversi.
 *
 * Il legame è a senso unico, come il cross-selling di WooCommerce da cui
 * arriva la richiesta: mettere B sotto A non mette A sotto B. Vale per un
 * prodotto alla volta, ed è la redazione a decidere entrambe le direzioni se
 * le vuole.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_related', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();

            $table->primary(['product_id', 'related_product_id'], 'prodotti_collegati_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_related');
    }
};

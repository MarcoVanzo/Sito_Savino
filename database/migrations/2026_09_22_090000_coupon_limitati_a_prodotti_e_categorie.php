<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un codice promozionale può valere solo su alcuni prodotti.
 *
 * Finora lo sconto si applicava sempre all'intero carrello: la redazione usa
 * invece codici legati a un singolo articolo (il compleanno di un'atleta, la
 * maglia di una gara) e senza questo vincolo un codice nato per una t-shirt
 * scontava anche il resto dell'ordine.
 *
 * Due elenchi invece di uno: le categorie evitano di dover riscrivere il
 * coupon a ogni prodotto nuovo dello stesso reparto. Valgono in somma — un
 * articolo è ammesso se compare fra i prodotti oppure se la sua categoria
 * compare fra le categorie — e un coupon senza nessuno dei due resta, come
 * prima, valido su tutto.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabelle ponte senza chiave surrogata né timestamp: la coppia di
        // identificativi è già la chiave, e un `id` in più renderebbe
        // ambiguo il `select id` con cui Eloquent carica gli elenchi.
        Schema::create('coupon_product', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->primary(['coupon_id', 'product_id']);
        });

        Schema::create('coupon_product_category', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();

            $table->primary(['coupon_id', 'product_category_id'], 'coupon_categoria_primary');
        });

        // `name` è dichiarata translatable sul model ma la colonna è rimasta
        // varchar(255) dall'importazione da WooCommerce: appena la redazione
        // scrive anche il nome inglese, il JSON `{"it":…,"en":…}` sfonda i 255
        // caratteri e MySQL rifiuta il salvataggio (errore 1406). La colonna
        // non è indicizzata, quindi allargarla non costa niente.
        Schema::table('products', function (Blueprint $table) {
            $table->text('name')->change();
        });
    }

    /**
     * L'allargamento di `products.name` non si annulla: restringere una
     * colonna troncherebbe i nomi già tradotti. Gli elenchi del coupon,
     * quelli sì, si buttano.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_product_category');
        Schema::dropIfExists('coupon_product');
    }
};

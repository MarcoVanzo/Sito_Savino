<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Aggiunta campi e-commerce alla tabella products
|--------------------------------------------------------------------------
|
| Aggiunge i campi necessari per la gestione avanzata dei prodotti:
| - type: tipo di prodotto (simple, variable, ecc.)
| - sale_price: prezzo scontato
| - sale_start / sale_end: finestra temporale dello sconto
| - short_description: descrizione breve per le card/listing
| - weight: peso del prodotto per il calcolo della spedizione
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Tipo di prodotto (simple, variable, ecc.)
            $table->string('type', 20)->default('simple')->after('is_active');

            // Prezzo scontato e finestra temporale dello sconto
            $table->decimal('sale_price', 10, 2)->nullable()->after('price');
            $table->timestamp('sale_start')->nullable()->after('sale_price');
            $table->timestamp('sale_end')->nullable()->after('sale_start');

            // Descrizione breve per card e listing
            $table->text('short_description')->nullable()->after('description');

            // Peso del prodotto (kg) per il calcolo della spedizione
            $table->decimal('weight', 8, 2)->nullable()->after('short_description');
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'sale_price',
                'sale_start',
                'sale_end',
                'short_description',
                'weight',
            ]);
        });
    }
};

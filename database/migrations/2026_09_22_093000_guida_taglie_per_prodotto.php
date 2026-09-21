<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ogni prodotto può avere la sua guida alle taglie.
 *
 * Il link sotto le taglie apriva una tabella scritta dentro il componente
 * Vue, uguale per tutti: la stessa misura valeva per una maglia gara e per
 * una t-shirt, e per i prodotti vecchi non c'era modo di toglierlo.
 *
 * La colonna tiene il percorso di uno dei PDF caricati in "Guida Taglie &
 * Contatti". Vuota significa "quella generale" (la pagina con tutti i
 * documenti, che sparisce da sé se non ce n'è nessuno), e il valore
 * `nessuna` nasconde del tutto la voce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('size_guide')->nullable()->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('size_guide');
        });
    }
};

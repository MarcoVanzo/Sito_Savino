<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La prova del testo accettato.
 *
 * `orders.condizioni_versione` è una costante del codice, ma le pagine delle
 * condizioni le modifica la redazione dal pannello, e il PDF allegato alla
 * conferma si rigenerava dal testo pubblicato al momento dell'invio: di
 * quello che il cliente aveva davanti al checkout non restava traccia.
 *
 * Ora ogni ordine porta l'impronta sha256 del testo (nella sua lingua) e il
 * testo sta in `versioni_condizioni`, una riga per impronta: cento ordini
 * con le stesse condizioni condividono la stessa istantanea.
 *
 * Guardie su tabella e colonna perché le migrazioni girano a ogni deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('versioni_condizioni')) {
            Schema::create('versioni_condizioni', function (Blueprint $table) {
                $table->id();
                $table->char('impronta', 64)->unique();
                $table->string('lingua', 5);
                $table->longText('html');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'condizioni_impronta')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->char('condizioni_impronta', 64)->nullable()->after('condizioni_versione');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'condizioni_impronta')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('condizioni_impronta');
            });
        }

        Schema::dropIfExists('versioni_condizioni');
    }
};

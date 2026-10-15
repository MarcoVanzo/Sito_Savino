<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I biglietti di una gara in casa, scritti dalla redazione nella scheda della
 * gara: finiscono nell'`offers` dei dati strutturati (SportsEvent). Le
 * sincronizzazioni di Lega e CEV scrivono solo le loro colonne, quindi questi
 * campi restano come li ha lasciati il pannello.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            if (! Schema::hasColumn('games', 'biglietti_url')) {
                $table->string('biglietti_url', 500)->nullable()->after('stream_url');
                $table->dateTime('biglietti_in_vendita_dal')->nullable()->after('biglietti_url');
                $table->boolean('biglietti_esauriti')->default(false)->after('biglietti_in_vendita_dal');
                $table->decimal('biglietti_prezzo_da', 8, 2)->nullable()->after('biglietti_esauriti');
            }
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['biglietti_url', 'biglietti_in_vendita_dal', 'biglietti_esauriti', 'biglietti_prezzo_da']);
        });
    }
};

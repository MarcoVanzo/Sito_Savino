<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Il testo alternativo che la redazione puo' scrivere per la copertina di una
 * notizia, il logo di uno sponsor e la foto di un'atleta.
 *
 * Facoltativo: vuoto, il sito ripiega sul titolo della notizia, sul nome dello
 * sponsor e su «Nome Cognome, Savino Del Bene Volley». Una colonna del record e
 * non una custom property della media: resta quando si sostituisce l'immagine
 * e il modulo la salva come ogni altro campo.
 *
 * Sempre `text` (§9): sulle notizie e' tradotta (spatie/translatable).
 */
return new class extends Migration
{
    // Una chiamata per tabella, scritte per esteso: Larastan legge lo schema
    // dalle migrazioni e non risolve il nome di una tabella in una variabile.
    public function up(): void
    {
        if (! Schema::hasColumn('posts', 'testo_alternativo')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->text('testo_alternativo')->nullable();
            });
        }

        if (! Schema::hasColumn('sponsors', 'testo_alternativo')) {
            Schema::table('sponsors', function (Blueprint $table) {
                $table->text('testo_alternativo')->nullable();
            });
        }

        if (! Schema::hasColumn('players', 'testo_alternativo')) {
            Schema::table('players', function (Blueprint $table) {
                $table->text('testo_alternativo')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['posts', 'sponsors', 'players'] as $tabella) {
            if (Schema::hasColumn($tabella, 'testo_alternativo')) {
                Schema::table($tabella, fn (Blueprint $table) => $table->dropColumn('testo_alternativo'));
            }
        }
    }
};

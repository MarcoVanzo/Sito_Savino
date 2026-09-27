<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendario, risultati e classifica della CEV Champions League.
 *
 * Arrivano dal vecchio portale competizioni della CEV (www-old.cev.eu), come
 * quelli del campionato arrivano dalla Lega. Servono due cose:
 *
 * - l'identificativo stabile della gara sul portale (`mID`), su cui fare upsert
 *   senza duplicare: e' il gemello di `lvf_match_id`;
 * - il girone della riga di classifica: in Champions la classifica e' quella
 *   del girone, e la pagina deve poter dire quale.
 *
 * Solo colonne nullable aggiunte: nessun dato esistente cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->unsignedBigInteger('cev_match_id')->nullable()->unique()->after('lvf_match_id');
        });

        Schema::table('standings', function (Blueprint $table) {
            $table->string('girone', 40)->nullable()->after('competition_type');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['cev_match_id']);
            $table->dropColumn('cev_match_id');
        });

        Schema::table('standings', function (Blueprint $table) {
            $table->dropColumn('girone');
        });
    }
};

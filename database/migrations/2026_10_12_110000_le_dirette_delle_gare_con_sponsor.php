<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diretta con sponsor: le impostazioni e lo stato della diretta di ogni gara.
 *
 * Una riga per gara (`game_id` unico): cosa va in onda prima della diretta,
 * ogni quanto la cornice compare durante la gara e cosa sta mandando la regia.
 * `versione` sale a ogni cambio di stato: il sito la confronta per sapere se
 * c'è qualcosa di nuovo. Le due tabelle ponte tengono l'ordine delle
 * creatività del pre-roll e della rotazione durante la gara.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('live_sessions')) {
            Schema::create('live_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('game_id')->unique()->constrained('games')->cascadeOnDelete();
                $table->boolean('preroll_attivo')->default(true);
                $table->unsignedTinyInteger('preroll_salta_dopo_secondi')->default(5);
                $table->boolean('ciclo_attivo')->default(true);
                $table->unsignedTinyInteger('ciclo_ogni_minuti')->default(10);
                $table->unsignedTinyInteger('ciclo_durata_secondi')->default(15);
                $table->unsignedSmallInteger('ciclo_ritardo_minuti')->default(5);
                $table->dateTime('ciclo_inizio')->nullable();
                $table->dateTime('ciclo_fine')->nullable();
                $table->unsignedSmallInteger('pausa_durata_predefinita_secondi')->default(180);
                $table->string('stato')->default('in_onda');
                $table->foreignId('creativita_in_onda_id')->nullable()->constrained('live_creatives')->nullOnDelete();
                $table->dateTime('pausa_fino_alle')->nullable();
                $table->unsignedInteger('versione')->default(1);
                $table->timestamps();
            });
        }

        foreach (['live_session_preroll', 'live_session_pausa'] as $ponte) {
            if (Schema::hasTable($ponte)) {
                continue;
            }

            Schema::create($ponte, function (Blueprint $table) {
                $table->id();
                $table->foreignId('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
                $table->foreignId('live_creative_id')->constrained('live_creatives')->cascadeOnDelete();
                $table->unsignedSmallInteger('ordine')->default(0);

                $table->unique(['live_session_id', 'live_creative_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('live_session_pausa');
        Schema::dropIfExists('live_session_preroll');
        Schema::dropIfExists('live_sessions');
    }
};

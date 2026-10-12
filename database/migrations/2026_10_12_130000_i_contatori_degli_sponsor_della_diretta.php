<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quante volte gli sponsor della diretta sono stati visti e cliccati, gara
 * per gara e giorno per giorno: il report da mostrare allo sponsor.
 *
 * Solo contatori: nessun identificativo, nessun IP, nessun cookie. Una riga
 * per (gara, creatività, evento, giorno), incrementata con un upsert atomico.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_ad_stats')) {
            return;
        }

        Schema::create('live_ad_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('live_creative_id')->constrained('live_creatives')->cascadeOnDelete();
            $table->string('evento', 32);
            $table->date('giorno');
            $table->unsignedInteger('conteggio')->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'live_creative_id', 'evento', 'giorno'], 'live_ad_stats_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_ad_stats');
    }
};

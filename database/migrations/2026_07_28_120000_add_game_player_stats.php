<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiche per singola gara, importate dal tabellino della Lega.
 *
 * Si importano le righe di ENTRAMBE le squadre, perché nella scheda della
 * partita servono anche i numeri delle avversarie. `player_id` è però valorizzato
 * solo per le nostre atlete: per le altre resta null e vale il nome così come lo
 * pubblica la Lega. Lo storico per giocatrice si costruisce quindi solo sulle
 * righe con `player_id`, senza dover creare anagrafiche di atlete altrui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            // Null per le atlete avversarie, che non hanno un'anagrafica nostra.
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();

            $table->string('player_name');
            $table->unsignedSmallInteger('jersey_number')->nullable();
            $table->boolean('is_captain')->default(false);
            $table->boolean('is_libero')->default(false);
            $table->unsignedTinyInteger('sets_played')->default(0);

            // Punti: totali, conquistati in break point, scarto vinti-persi.
            $table->integer('points_total')->default(0);
            $table->integer('points_break')->default(0);
            $table->integer('points_win_loss')->default(0);

            $table->integer('serve_total')->default(0);
            $table->integer('serve_errors')->default(0);
            $table->integer('serve_points')->default(0);

            $table->integer('reception_total')->default(0);
            $table->integer('reception_errors')->default(0);
            $table->unsignedTinyInteger('reception_positive_pct')->nullable();
            $table->unsignedTinyInteger('reception_perfect_pct')->nullable();

            $table->integer('attack_total')->default(0);
            $table->integer('attack_errors')->default(0);
            $table->integer('attack_blocked')->default(0);
            $table->integer('attack_points')->default(0);
            $table->unsignedTinyInteger('attack_pct')->nullable();

            $table->integer('block_points')->default(0);

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            // Il numero di maglia identifica l'atleta all'interno della gara;
            // resta unico anche quando l'anagrafica non è nostra.
            $table->unique(['game_id', 'team_id', 'player_name'], 'uq_game_player_stats_row');
            $table->index(['player_id', 'game_id'], 'idx_game_player_stats_player');
        });

        Schema::table('games', function (Blueprint $table) {
            // Dati di referto presenti solo nel tabellino.
            $table->unsignedInteger('spectators')->nullable()->after('location');
            $table->string('referees')->nullable()->after('spectators');
            // Parziali per set: [{"set":1,"duration":28,"partials":["8-7","16-14"],"home":23,"away":25}]
            $table->json('set_scores')->nullable()->after('referees');
            $table->timestamp('stats_synced_at')->nullable()->after('lvf_synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_stats');

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['spectators', 'referees', 'set_scores', 'stats_synced_at']);
        });
    }
};

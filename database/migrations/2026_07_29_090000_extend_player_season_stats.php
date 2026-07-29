<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estende i totali di stagione delle atlete a tutto ciò che il tabellino espone.
 *
 * `player_stats` nasceva con cinque numeri (punti, muri, ace, attacchi,
 * ricezioni), mentre il referto della Lega porta molto di più: presenze, set
 * giocati, errori e percentuali per fondamentale. Senza questi campi la scheda
 * di una giocatrice resta molto più povera del singolo tabellino da cui è
 * ricavata.
 *
 * Si aggiunge anche `team_id`: un'atleta può essere schierata in più squadre
 * della società nella stessa stagione, e i totali vanno tenuti distinti per
 * squadra, non sommati insieme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('season_id')->constrained('teams')->nullOnDelete();

            $table->unsignedSmallInteger('matches_played')->default(0)->after('team_id');
            $table->unsignedSmallInteger('sets_played')->default(0)->after('matches_played');

            $table->integer('points_break')->default(0)->after('points');

            $table->integer('serve_total')->default(0)->after('aces');
            $table->integer('serve_errors')->default(0)->after('serve_total');

            $table->integer('attack_errors')->default(0)->after('attacks');
            $table->integer('attack_blocked')->default(0)->after('attack_errors');
            $table->integer('attack_points')->default(0)->after('attack_blocked');
            // Ricalcolata dai totali (punti/attacchi), quindi esatta.
            $table->unsignedTinyInteger('attack_pct')->nullable()->after('attack_points');

            $table->integer('reception_errors')->default(0)->after('receptions');
            // Stimate: il tabellino espone solo le percentuali per gara, non il
            // numero assoluto di ricezioni positive e perfette. Si fa quindi una
            // media pesata sul volume di ricezione, che approssima il dato reale
            // a meno degli arrotondamenti della Lega.
            $table->unsignedTinyInteger('reception_positive_pct')->nullable()->after('reception_errors');
            $table->unsignedTinyInteger('reception_perfect_pct')->nullable()->after('reception_positive_pct');
        });

        // Le righe già in archivio non hanno la squadra: si ricava dalla rosa di
        // quella stagione. Senza questo passaggio la sincronizzazione successiva
        // non le riconoscerebbe — la chiave univoca è cambiata — e ne creerebbe
        // di nuove accanto, lasciando ogni atleta duplicata.
        DB::table('player_stats')
            ->whereNull('team_id')
            ->update([
                'team_id' => DB::raw(
                    '(SELECT r.team_id FROM rosters r
                       WHERE r.player_id = player_stats.player_id
                         AND r.season_id = player_stats.season_id
                       ORDER BY r.id LIMIT 1)'
                ),
            ]);

        Schema::table('player_stats', function (Blueprint $table) {
            // I totali sono ora per squadra oltre che per stagione.
            $table->dropUnique('player_stats_player_id_season_id_unique');
            $table->unique(['player_id', 'season_id', 'team_id'], 'uq_player_stats_player_season_team');
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropUnique('uq_player_stats_player_season_team');
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropColumn([
                'matches_played', 'sets_played', 'points_break',
                'serve_total', 'serve_errors',
                'attack_errors', 'attack_blocked', 'attack_points', 'attack_pct',
                'reception_errors', 'reception_positive_pct', 'reception_perfect_pct',
            ]);
            $table->unique(['player_id', 'season_id']);
        });
    }
};

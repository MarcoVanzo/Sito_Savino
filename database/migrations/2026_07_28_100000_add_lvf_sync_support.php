<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supporto alla sincronizzazione con il sito della Lega Volley Femminile.
 *
 * Le partite e la classifica arrivano da legavolleyfemminile.it. Servono tre cose:
 * un identificativo stabile per ogni entità remota (per fare upsert idempotenti
 * senza duplicare a ogni sync), i metadati di giornata/fase che il calendario
 * espone, e una tabella per la classifica, che prima non esisteva affatto.
 *
 * `teams` ospita sia le squadre del club (is_internal = true) sia gli avversari
 * della Lega: lo schema lo prevedeva già, dato che games.home_team_id e
 * away_team_id puntano entrambi qui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('lvf_club_id')->nullable()->unique()->after('is_internal');
            $table->string('logo_url')->nullable()->after('lvf_club_id');
        });

        // Finché la sincronizzazione non gira, `teams` contiene solo le squadre
        // del club: gli avversari della Lega non sono mai stati importati. Vanno
        // marcate come interne adesso, altrimenti la prima sincronizzazione non
        // riconosce la squadra di casa e ne crea un duplicato, spezzando il
        // legame con rose e statistiche già inserite dal CMS.
        DB::table('teams')->update(['is_internal' => true]);

        Schema::table('games', function (Blueprint $table) {
            $table->unsignedBigInteger('lvf_match_id')->nullable()->unique()->after('id');
            $table->unsignedSmallInteger('matchday')->nullable()->after('competition_type');
            $table->string('phase')->nullable()->after('matchday');
            $table->timestamp('lvf_synced_at')->nullable()->after('phase');
        });

        Schema::table('seasons', function (Blueprint $table) {
            // Anno di apertura usato dalla Lega come parametro `stagione`:
            // la stagione 2026/2027 è `stagione=2026`.
            $table->unsignedSmallInteger('lvf_season_year')->nullable()->unique()->after('is_current');
        });

        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('competition_type')->default('Campionato');

            $table->unsignedSmallInteger('position');
            $table->integer('points')->default(0);
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('won')->default(0);
            $table->unsignedSmallInteger('lost')->default(0);

            // Ripartizione dei risultati per punteggio in set, come la espone la Lega.
            $table->unsignedSmallInteger('won_3_0')->default(0);
            $table->unsignedSmallInteger('won_3_1')->default(0);
            $table->unsignedSmallInteger('won_3_2')->default(0);
            $table->unsignedSmallInteger('lost_2_3')->default(0);
            $table->unsignedSmallInteger('lost_1_3')->default(0);
            $table->unsignedSmallInteger('lost_0_3')->default(0);

            $table->unsignedSmallInteger('sets_won')->default(0);
            $table->unsignedSmallInteger('sets_lost')->default(0);
            $table->unsignedInteger('points_for')->default(0);
            $table->unsignedInteger('points_against')->default(0);

            // Quozienti set e punti: la Lega li pubblica già calcolati a 2 decimali.
            $table->decimal('set_ratio', 6, 2)->nullable();
            $table->decimal('point_ratio', 6, 2)->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'competition_type', 'team_id'], 'uq_standings_season_competition_team');
            $table->index(['season_id', 'competition_type', 'position'], 'idx_standings_ordering');
        });
    }

    public function down(): void
    {
        // I dati importati vanno rimossi PRIMA di eliminare le colonne che li
        // identificano, altrimenti restano in archivio senza più essere
        // riconoscibili: al rimigrare, `up()` marcherebbe come squadre del club
        // anche gli avversari, e le gare — perso `lvf_match_id` — verrebbero
        // reimportate in duplicato. Sono dati ricostruibili con `lvf:sync`.
        Schema::dropIfExists('standings');

        DB::table('games')->whereNotNull('lvf_match_id')->delete();
        DB::table('teams')->whereNotNull('lvf_club_id')->where('is_internal', false)->delete();

        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('lvf_season_year');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['lvf_match_id', 'matchday', 'phase', 'lvf_synced_at']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['lvf_club_id', 'logo_url']);
        });
    }
};

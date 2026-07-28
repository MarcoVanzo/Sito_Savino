<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alias degli identificativi di club usati dalla Lega.
 *
 * La Lega NON usa un identificativo stabile per società: lo cambia a ogni
 * stagione. Il Savino Del Bene è `710955` nel 2026/2027 e `710918` nel
 * 2025/2026, e lo stesso vale per tutte le altre. Con il solo
 * `teams.lvf_club_id` ogni stagione importata creerebbe una squadra duplicata,
 * spezzando classifica, gare e statistiche storiche.
 *
 * Qui si tiene l'elenco completo degli identificativi noti per ciascuna squadra;
 * `teams.lvf_club_id` resta come "identificativo corrente".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_lvf_club_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('lvf_club_id')->unique();
            $table->unsignedSmallInteger('season_year')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'season_year'], 'idx_team_lvf_club_ids_team');
        });

        // Gli identificativi già assegnati diventano il primo alias.
        $now = now();
        $rows = DB::table('teams')
            ->whereNotNull('lvf_club_id')
            ->get(['id', 'lvf_club_id'])
            ->map(fn ($team) => [
                'team_id' => $team->id,
                'lvf_club_id' => $team->lvf_club_id,
                'season_year' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('team_lvf_club_ids')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_lvf_club_ids');
    }
};

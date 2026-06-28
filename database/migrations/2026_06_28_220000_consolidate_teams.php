<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Consolidate teams: keep "Savino Del Bene Volley" as the single team,
     * remove the duplicate "Serie A1" team.
     *
     * Handles both scenarios:
     * - Production: has both "Savino Del Bene Volley" and "Serie A1"
     * - Local/fresh: has only "Serie A1" (rename it)
     */
    public function up(): void
    {
        $sdb = DB::table('teams')->whereNull('deleted_at')->where('name', 'Savino Del Bene Volley')->first();
        $serieA1 = DB::table('teams')->where('slug', 'serie-a1')->first(); // includes soft-deleted

        if ($sdb && $serieA1 && $sdb->id !== $serieA1->id) {
            // Production scenario: both exist — move rosters from "Serie A1" to "Savino Del Bene Volley"
            Log::info("Consolidating teams: moving rosters from Serie A1 (ID:{$serieA1->id}) to Savino Del Bene Volley (ID:{$sdb->id})");

            // Move rosters that don't already exist on the target team
            $existingRosters = DB::table('rosters')
                ->where('team_id', $sdb->id)
                ->get(['player_id', 'season_id'])
                ->map(fn ($r) => $r->player_id . '-' . $r->season_id)
                ->toArray();

            DB::table('rosters')
                ->where('team_id', $serieA1->id)
                ->get()
                ->each(function ($roster) use ($sdb, $existingRosters) {
                    $key = $roster->player_id . '-' . $roster->season_id;
                    if (!in_array($key, $existingRosters)) {
                        DB::table('rosters')->where('id', $roster->id)->update(['team_id' => $sdb->id]);
                    } else {
                        // Duplicate — delete the one from Serie A1
                        DB::table('rosters')->where('id', $roster->id)->delete();
                    }
                });

            // Move home/away games
            DB::table('games')->where('home_team_id', $serieA1->id)->update(['home_team_id' => $sdb->id]);
            DB::table('games')->where('away_team_id', $serieA1->id)->update(['away_team_id' => $sdb->id]);

            // Hard-delete the Serie A1 team
            DB::table('teams')->where('id', $serieA1->id)->delete();

            Log::info("Team Serie A1 (ID:{$serieA1->id}) removed. All data moved to Savino Del Bene Volley (ID:{$sdb->id}).");

        } elseif (!$sdb && $serieA1) {
            // Local/fresh scenario: only "Serie A1" exists — rename it
            Log::info("Renaming team Serie A1 (ID:{$serieA1->id}) to Savino Del Bene Volley");

            DB::table('teams')->where('id', $serieA1->id)->update([
                'name' => 'Savino Del Bene Volley',
                'slug' => 'savino-del-bene-volley',
                'category' => 'A1',
                'deleted_at' => null, // restore if soft-deleted
            ]);
        } else {
            Log::info('Team consolidation: no action needed.');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse data consolidation
    }
};

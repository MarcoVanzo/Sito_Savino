<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Clean up duplicate players created by Roster2026Seeder.
     *
     * The DatabaseSeeder created players using lega_volley_id as key for some athletes,
     * while Roster2026Seeder looked them up by first_name/last_name and created duplicates.
     */
    public function up(): void
    {
        $duplicates = DB::select("
            SELECT first_name, last_name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids
            FROM players
            WHERE deleted_at IS NULL
            GROUP BY first_name, last_name
            HAVING cnt > 1
        ");

        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup->ids);
            // Keep the first (oldest) record, delete the rest
            $keepId = (int) array_shift($ids);
            $deleteIds = array_map('intval', $ids);

            Log::info("Cleaning duplicate player: {$dup->first_name} {$dup->last_name}", [
                'keeping' => $keepId,
                'deleting' => $deleteIds,
            ]);

            // Re-assign any roster entries pointing to duplicates
            DB::table('rosters')
                ->whereIn('player_id', $deleteIds)
                ->update(['player_id' => $keepId]);

            // Re-assign any player_stats entries pointing to duplicates
            DB::table('player_stats')
                ->whereIn('player_id', $deleteIds)
                ->update(['player_id' => $keepId]);

            // Delete duplicate roster entries that now conflict on unique keys
            // (keep the one with the lowest id for each team_id/season_id/player_id combo)
            $dupeRosters = DB::select("
                SELECT GROUP_CONCAT(id ORDER BY id) as ids
                FROM rosters
                WHERE player_id = ?
                GROUP BY team_id, season_id, player_id
                HAVING COUNT(*) > 1
            ", [$keepId]);

            foreach ($dupeRosters as $dr) {
                $rosterIds = explode(',', $dr->ids);
                array_shift($rosterIds); // keep the first
                if (!empty($rosterIds)) {
                    DB::table('rosters')->whereIn('id', array_map('intval', $rosterIds))->delete();
                }
            }

            // Delete the duplicate player records
            DB::table('players')->whereIn('id', $deleteIds)->delete();
        }

        $remaining = DB::table('players')->whereNull('deleted_at')->count();
        Log::info("Player cleanup complete. Remaining players: {$remaining}");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse data cleanup
    }
};

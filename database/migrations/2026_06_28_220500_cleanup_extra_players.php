<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Remove extra players that don't belong to the official roster.
     * Production has 21 players but only 14 are correct.
     */
    public function up(): void
    {
        $validPlayers = [
            ['Maja', 'Ognjenović'],
            ['Chidera', 'Eze'],
            ['Kiera', 'Van Ryk'],
            ['Lise', 'Van Hecke'],
            ['Avery', 'Skinner'],
            ['Sofia', "D'Odorico"],
            ['Caterina', 'Bosetti'],
            ['Julia', 'Bergmann'],
            ['Sara', 'Alberti'],
            ['Maja', 'Aleksić'],
            ['Emma', 'Graziani'],
            ['Linda', 'Nwakalor'],
            ['Imma', 'Sirressi'],
            ['Martina', 'Armini'],
        ];

        // Build list of valid player IDs
        $validIds = [];
        foreach ($validPlayers as [$firstName, $lastName]) {
            $player = DB::table('players')
                ->where('first_name', $firstName)
                ->where('last_name', $lastName)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if ($player) {
                $validIds[] = $player->id;
            }
        }

        if (empty($validIds)) {
            Log::warning('cleanup_extra_players: no valid players found, skipping.');
            return;
        }

        // Find extra players
        $extraPlayers = DB::table('players')
            ->whereNull('deleted_at')
            ->whereNotIn('id', $validIds)
            ->get();

        Log::info("cleanup_extra_players: found {$extraPlayers->count()} extra players to remove.");

        foreach ($extraPlayers as $player) {
            Log::info("Removing extra player: {$player->first_name} {$player->last_name} (ID:{$player->id})");

            // Remove associated roster entries
            DB::table('rosters')->where('player_id', $player->id)->delete();

            // Remove associated stats
            DB::table('player_stats')->where('player_id', $player->id)->delete();

            // Hard-delete the player
            DB::table('players')->where('id', $player->id)->delete();
        }

        $remaining = DB::table('players')->whereNull('deleted_at')->count();
        Log::info("cleanup_extra_players complete. Remaining players: {$remaining}");
    }

    public function down(): void
    {
        // Cannot reverse data cleanup
    }
};

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Creazione Stagione
        $season = \App\Models\Season::create([
            'name' => '2026/2027',
            'is_current' => true
        ]);

        // Creazione Squadra
        $team = \App\Models\Team::create([
            'name' => 'Serie A1',
            'slug' => 'serie-a1',
            'category' => 'Professionistico'
        ]);

        // Giocatrice 1: Avery Skinner
        $skinner = \App\Models\Player::create([
            'first_name' => 'Avery',
            'last_name' => 'Skinner',
            'date_of_birth' => '1999-04-25',
            'nationality' => 'USA',
            'instagram_handle' => '@averyskinnerr',
            'lega_volley_id' => 101
        ]);

        \App\Models\Roster::create([
            'player_id' => $skinner->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 11,
            'role' => 'Schiacciatrice',
            'height_cm' => 186,
            'is_captain' => false,
            'official_photo_url' => 'skinner_official.jpg',
            'action_photo_url' => 'skinner_action.jpg'
        ]);

        \App\Models\PlayerStat::create([
            'player_id' => $skinner->id,
            'season_id' => $season->id,
            'points' => 312,
            'blocks' => 24,
            'aces' => 18,
            'attacks' => 270,
            'receptions' => 450,
            'last_synced_at' => now()
        ]);

        // Giocatrice 2: Rosalba Bergman
        $bergman = \App\Models\Player::create([
            'first_name' => 'Rosalba',
            'last_name' => 'Bergman',
            'date_of_birth' => '2001-08-14',
            'nationality' => 'Olanda',
            'instagram_handle' => '@rosalbabergman',
            'lega_volley_id' => 102
        ]);

        \App\Models\Roster::create([
            'player_id' => $bergman->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 4,
            'role' => 'Opposta',
            'height_cm' => 192,
            'is_captain' => false
        ]);

        // Giocatrice 3: Maja Ognjenović
        $maja = \App\Models\Player::create([
            'first_name' => 'Maja',
            'last_name' => 'Ognjenović',
            'date_of_birth' => '1984-08-06',
            'nationality' => 'Serbia',
            'instagram_handle' => '@majaognjenovic10',
            'lega_volley_id' => 103
        ]);

        \App\Models\Roster::create([
            'player_id' => $maja->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 10,
            'role' => 'Palleggiatrice',
            'height_cm' => 183,
            'is_captain' => true,
            'bio' => 'La leader carismatica della Savino Del Bene, Maja guida il gruppo con la sua esperienza immensa.'
        ]);
        
        // Utente Admin per accesso
        \App\Models\User::factory()->create([
            'name' => 'Admin Savino',
            'email' => 'admin@savinodelbene.it',
            'password' => bcrypt('password')
        ]);
    }
}

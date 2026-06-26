<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
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
            'nationality' => 'USA',
            'lega_volley_id' => 101
        ]);
        \App\Models\Roster::create([
            'player_id' => $skinner->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 11,
            'role' => 'Schiacciatrice',
            'is_captain' => false,
            'official_photo_url' => 'images/roster/Avery Skinner.JPG'
        ]);

        // Giocatrice 2: Caterina Bosetti
        $bosetti = \App\Models\Player::create([
            'first_name' => 'Caterina',
            'last_name' => 'Bosetti',
            'nationality' => 'Italia',
            'lega_volley_id' => 102
        ]);
        \App\Models\Roster::create([
            'player_id' => $bosetti->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 9,
            'role' => 'Schiacciatrice',
            'is_captain' => false,
            'official_photo_url' => 'images/roster/Caterina Bosetti .jpg'
        ]);

        // Giocatrice 3: Emma Graziani
        $graziani = \App\Models\Player::create([
            'first_name' => 'Emma',
            'last_name' => 'Graziani',
            'nationality' => 'Italia',
            'lega_volley_id' => 103
        ]);
        \App\Models\Roster::create([
            'player_id' => $graziani->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 14,
            'role' => 'Centrale',
            'is_captain' => false,
            'official_photo_url' => 'images/roster/Emma Graziani.JPG'
        ]);

        // Giocatrice 4: Linda Nwakalor
        $nwakalor = \App\Models\Player::create([
            'first_name' => 'Linda',
            'last_name' => 'Nwakalor',
            'nationality' => 'Italia',
            'lega_volley_id' => 104
        ]);
        \App\Models\Roster::create([
            'player_id' => $nwakalor->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 15,
            'role' => 'Centrale',
            'is_captain' => false,
            'official_photo_url' => 'images/roster/Linda Nwakalor.JPG'
        ]);

        // Giocatrice 5: Maja Ognjenović
        $maja = \App\Models\Player::create([
            'first_name' => 'Maja',
            'last_name' => 'Ognjenović',
            'nationality' => 'Serbia',
            'lega_volley_id' => 105
        ]);
        \App\Models\Roster::create([
            'player_id' => $maja->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 10,
            'role' => 'Palleggiatrice',
            'is_captain' => true,
            'bio' => 'La leader carismatica della Savino Del Bene, Maja guida il gruppo con la sua esperienza immensa.',
            'official_photo_url' => 'images/roster/Maja Ognjenović.jpg'
        ]);
        
        // Utente Admin per accesso
        \App\Models\User::factory()->create([
            'name' => 'Admin Savino',
            'email' => 'admin@savinodelbene.it',
            'password' => bcrypt('password')
        ]);
    }
}

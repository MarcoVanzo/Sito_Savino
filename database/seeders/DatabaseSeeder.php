<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Creazione Stagione
        $season = \App\Models\Season::firstOrCreate(
            ['name' => '2026/2027'],
            ['is_current' => true]
        );

        // Creazione Squadra
        $team = \App\Models\Team::firstOrCreate(
            ['slug' => 'serie-a1'],
            ['name' => 'Serie A1', 'category' => 'Professionistico']
        );

        // Giocatrice 1: Avery Skinner
        $skinner = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 101],
            ['first_name' => 'Avery', 'last_name' => 'Skinner', 'nationality' => 'USA']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $skinner->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 11, 'role' => 'schiacciatrice', 'is_captain' => false]
        );

        // Giocatrice 2: Caterina Bosetti
        $bosetti = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 102],
            ['first_name' => 'Caterina', 'last_name' => 'Bosetti', 'nationality' => 'Italia']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $bosetti->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 9, 'role' => 'schiacciatrice', 'is_captain' => false]
        );

        // Giocatrice 3: Emma Graziani
        $graziani = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 103],
            ['first_name' => 'Emma', 'last_name' => 'Graziani', 'nationality' => 'Italia']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $graziani->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 14, 'role' => 'centrale', 'is_captain' => false]
        );

        // Giocatrice 4: Linda Nwakalor
        $nwakalor = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 104],
            ['first_name' => 'Linda', 'last_name' => 'Nwakalor', 'nationality' => 'Italia']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $nwakalor->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 15, 'role' => 'centrale', 'is_captain' => false]
        );

        // Giocatrice 5: Maja Ognjenović
        $maja = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 105],
            ['first_name' => 'Maja', 'last_name' => 'Ognjenović', 'nationality' => 'Serbia']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $maja->id, 'team_id' => $team->id, 'season_id' => $season->id],
            [
                'jersey_number' => 10,
                'role' => 'palleggiatrice',
                'is_captain' => true,
                'bio' => 'La leader carismatica della Savino Del Bene, Maja guida il gruppo con la sua esperienza immensa.',
            ]
        );
        
        // Utente Admin per accesso
        \App\Models\User::factory()->create([
            'name' => 'Admin Savino',
            'email' => 'admin@savinodelbene.it',
            'password' => 'password', // Il cast 'hashed' su User hasha automaticamente
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->call([
            PageSeeder::class,
        ]);
    }
}

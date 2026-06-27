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

        // ── PALLEGGIATRICI ──────────────────────────────────────────────

        // #10 Maja Ognjenović
        $ognjenovic = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 40],
            ['first_name' => 'Maja', 'last_name' => 'Ognjenović', 'nationality' => 'Serbia', 'date_of_birth' => '1984-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $ognjenovic->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 10, 'role' => 'palleggiatrice', 'height_cm' => 185, 'is_captain' => true]
        );

        // #20 Chidera Eze
        $eze = \App\Models\Player::firstOrCreate(
            ['first_name' => 'Chidera', 'last_name' => 'Eze'],
            ['nationality' => 'Italia', 'date_of_birth' => '2003-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $eze->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 20, 'role' => 'palleggiatrice', 'height_cm' => 182, 'is_captain' => false]
        );

        // ── OPPOSTE ────────────────────────────────────────────────────

        // #3 Kiera Van Ryk
        $vanryk = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 600],
            ['first_name' => 'Kiera', 'last_name' => 'Van Ryk', 'nationality' => 'Canada', 'date_of_birth' => '1999-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $vanryk->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 3, 'role' => 'opposto', 'height_cm' => 188, 'is_captain' => false]
        );

        // #7 Lise Van Hecke
        $vanhecke = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 331],
            ['first_name' => 'Lise', 'last_name' => 'Van Hecke', 'nationality' => 'Belgio', 'date_of_birth' => '1992-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $vanhecke->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 7, 'role' => 'opposto', 'height_cm' => 191, 'is_captain' => false]
        );

        // ── SCHIACCIATRICI ─────────────────────────────────────────────

        // #4 Avery Skinner
        $skinner = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 931],
            ['first_name' => 'Avery', 'last_name' => 'Skinner', 'nationality' => 'Stati Uniti', 'date_of_birth' => '1999-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $skinner->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 4, 'role' => 'schiacciatrice', 'height_cm' => 186, 'is_captain' => false]
        );

        // #6 Sofia D'Odorico
        $dodorico = \App\Models\Player::firstOrCreate(
            ['first_name' => 'Sofia', 'last_name' => "D'Odorico"],
            ['nationality' => 'Italia', 'date_of_birth' => '1997-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $dodorico->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 6, 'role' => 'schiacciatrice', 'height_cm' => 186, 'is_captain' => false]
        );

        // #9 Caterina Bosetti
        $bosetti = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 157],
            ['first_name' => 'Caterina', 'last_name' => 'Bosetti', 'nationality' => 'Italia', 'date_of_birth' => '1994-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $bosetti->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 9, 'role' => 'schiacciatrice', 'height_cm' => 180, 'is_captain' => false]
        );

        // #17 Julia Bergmann
        $bergmann = \App\Models\Player::firstOrCreate(
            ['first_name' => 'Julia', 'last_name' => 'Bergmann'],
            ['nationality' => 'Brasile', 'date_of_birth' => '2001-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $bergmann->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 17, 'role' => 'schiacciatrice', 'height_cm' => 192, 'is_captain' => false]
        );

        // ── CENTRALI ───────────────────────────────────────────────────

        // #2 Sara Alberti
        $alberti = \App\Models\Player::firstOrCreate(
            ['first_name' => 'Sara', 'last_name' => 'Alberti'],
            ['nationality' => 'Italia', 'date_of_birth' => '1993-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $alberti->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 2, 'role' => 'centrale', 'height_cm' => 187, 'is_captain' => false]
        );

        // #8 Maja Aleksić
        $aleksic = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 270],
            ['first_name' => 'Maja', 'last_name' => 'Aleksić', 'nationality' => 'Serbia', 'date_of_birth' => '1997-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $aleksic->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 8, 'role' => 'centrale', 'height_cm' => 188, 'is_captain' => false]
        );

        // #13 Emma Graziani
        $graziani = \App\Models\Player::firstOrCreate(
            ['first_name' => 'Emma', 'last_name' => 'Graziani'],
            ['nationality' => 'Italia', 'date_of_birth' => '2002-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $graziani->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 13, 'role' => 'centrale', 'height_cm' => 194, 'is_captain' => false]
        );

        // #14 Linda Nwakalor
        $nwakalor = \App\Models\Player::firstOrCreate(
            ['first_name' => 'Linda', 'last_name' => 'Nwakalor'],
            ['nationality' => 'Italia', 'date_of_birth' => '2002-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $nwakalor->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 14, 'role' => 'centrale', 'height_cm' => 188, 'is_captain' => false]
        );

        // ── LIBERI ─────────────────────────────────────────────────────

        // #5 Imma Sirressi
        $sirressi = \App\Models\Player::firstOrCreate(
            ['lega_volley_id' => 982],
            ['first_name' => 'Imma', 'last_name' => 'Sirressi', 'nationality' => 'Italia', 'date_of_birth' => '1990-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $sirressi->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 5, 'role' => 'libero', 'height_cm' => 175, 'is_captain' => false]
        );

        // #15 Martina Armini
        $armini = \App\Models\Player::firstOrCreate(
            ['first_name' => 'Martina', 'last_name' => 'Armini'],
            ['nationality' => 'Italia', 'date_of_birth' => '2002-01-01']
        );
        \App\Models\Roster::firstOrCreate(
            ['player_id' => $armini->id, 'team_id' => $team->id, 'season_id' => $season->id],
            ['jersey_number' => 15, 'role' => 'libero', 'height_cm' => 175, 'is_captain' => false]
        );
        
        // Utente Admin per accesso
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@savinodelbene.it'],
            [
                'name' => 'Admin Savino',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $this->call([
            PageSeeder::class,
        ]);
    }
}

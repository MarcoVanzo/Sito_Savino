<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\Roster;
use App\Models\Team;
use App\Models\Season;
use Illuminate\Database\Seeder;

class Roster2026Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [10, "Maja Ognjenović", "Serbia", "#40", "palleggiatrice", 185, 1984],
            [20, "Chidera Eze", "Italia", "", "palleggiatrice", 182, 2003],
            [3, "Kiera Van Ryk", "Canada", "#599", "opposto", 188, 1999], // opposta -> opposto
            [7, "Lise Van Hecke", "Belgio", "#331", "opposto", 191, 1992],
            [4, "Avery Skinner", "Stati Uniti", "#931", "schiacciatrice", 186, 1999],
            [6, "Sofia D'Odorico", "Italia", "", "schiacciatrice", 186, 1997],
            [9, "Caterina Bosetti", "Italia", "#157", "schiacciatrice", 180, 1994],
            [17, "Julia Bergmann", "Brasile", "", "schiacciatrice", 192, 2001],
            [2, "Sara Alberti", "Italia", "", "centrale", 187, 1993],
            [8, "Maja Aleksić", "Serbia", "#270", "centrale", 188, 1997],
            [13, "Emma Graziani", "Italia", "", "centrale", 194, 2002],
            [14, "Linda Nwakalor", "Italia", "", "centrale", 188, 2002],
            [5, "Imma Sirressi", "Italia", "#982", "libero", 175, 1990],
            [15, "Martina Armini", "Italia", "", "libero", 175, 2002]
        ];

        $team = Team::firstOrCreate(
            ['slug' => 'serie-a1'],
            ['name' => 'Serie A1', 'category' => 'Professionistico', 'is_internal' => false]
        );

        $season = Season::firstOrCreate(
            ['name' => '2026/2027'],
            ['is_current' => true]
        );

        foreach ($data as $row) {
            $parts = explode(" ", $row[1]);
            $firstName = $parts[0];
            $lastName = implode(" ", array_slice($parts, 1));
            
            // Trova o crea il giocatore
            $player = Player::where('first_name', $firstName)
                            ->where('last_name', $lastName)
                            ->first();
                            
            if (!$player) {
                $player = Player::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'nationality' => $row[2],
                    'date_of_birth' => $row[6] . '-01-01', // Approx
                ]);
            } else {
                // Aggiorna dettagli se mancano
                $player->update([
                    'nationality' => $row[2],
                    'date_of_birth' => $row[6] . '-01-01',
                ]);
            }
            
            // Associa il giocatore al Roster
            Roster::updateOrCreate(
                [
                    'team_id' => $team->id,
                    'season_id' => $season->id,
                    'player_id' => $player->id,
                ],
                [
                    'jersey_number' => $row[0],
                    'role' => $row[4],
                    'height_cm' => $row[5],
                ]
            );
        }
    }
}

<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Regressione: il template Public/Roster leggeva `shirt_number` e `player.role`,
     * colonne inesistenti — numero di maglia e ruolo arrivavano sempre vuoti.
     * I dati corretti stanno su `rosters.jersey_number` e `rosters.role`.
     */
    public function test_roster_page_exposes_jersey_number_and_role(): void
    {
        $season = Season::factory()->current()->create();
        $team = Team::factory()->create(['category' => 'A1']);
        $player = Player::factory()->create(['first_name' => 'Giulia', 'last_name' => 'Rossi']);

        Roster::factory()->create([
            'player_id' => $player->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 12,
            'role' => 'libero',
        ]);

        Page::factory()->create([
            'slug' => 'roster-test',
            'title' => 'Roster',
            'status' => PostStatus::Published,
            'template' => 'Public/Roster',
        ]);

        $this->get('/roster-test')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Public/Roster')
                ->has('players', 1)
                ->where('players.0.number', 12)
                ->where('players.0.role', 'libero')
                ->where('players.0.first_name', 'Giulia')
            );
    }

    /**
     * Il roster deve essere ordinato per numero di maglia, non nell'ordine
     * (non deterministico) restituito dal database.
     */
    public function test_roster_page_is_ordered_by_jersey_number(): void
    {
        $season = Season::factory()->current()->create();
        $team = Team::factory()->create(['category' => 'A1']);

        foreach ([9, 3, 21] as $number) {
            Roster::factory()->create([
                'player_id' => Player::factory(),
                'team_id' => $team->id,
                'season_id' => $season->id,
                'jersey_number' => $number,
            ]);
        }

        Page::factory()->create([
            'slug' => 'roster-ordinato',
            'title' => 'Roster',
            'status' => PostStatus::Published,
            'template' => 'Public/Roster',
        ]);

        $this->get('/roster-ordinato')
            ->assertInertia(fn ($page) => $page
                ->where('players.0.number', 3)
                ->where('players.1.number', 9)
                ->where('players.2.number', 21)
            );
    }
}

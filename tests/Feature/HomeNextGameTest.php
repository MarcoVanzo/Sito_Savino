<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Il calendario importato contiene tutto il campionato: senza il vincolo sulla
 * squadra interna la home mostrava come "prossima partita" una gara fra due
 * avversarie, per giunta con lo stemma del Savino sulla squadra di casa.
 */
class HomeNextGameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function nextGameProp(): ?array
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        return $response->viewData('page')['props']['nextGame'] ?? null;
    }

    public function test_the_home_shows_the_next_match_of_the_club_not_of_two_opponents(): void
    {
        $internal = Team::factory()->create(['is_internal' => true]);
        $opponentA = Team::factory()->create(['is_internal' => false]);
        $opponentB = Team::factory()->create(['is_internal' => false]);

        $betweenOpponents = Game::factory()->create([
            'home_team_id' => $opponentA->id,
            'away_team_id' => $opponentB->id,
            'match_date' => now()->addDays(2),
            'status' => GameStatus::Scheduled,
        ]);

        $ourGame = Game::factory()->create([
            'home_team_id' => $internal->id,
            'away_team_id' => $opponentA->id,
            'match_date' => now()->addDays(5),
            'status' => GameStatus::Scheduled,
        ]);

        $nextGame = $this->nextGameProp();

        $this->assertNotNull($nextGame);
        $this->assertSame($ourGame->id, $nextGame['id']);
        $this->assertNotSame($betweenOpponents->id, $nextGame['id']);
    }

    public function test_the_next_match_carries_the_logo_of_each_team(): void
    {
        $internal = Team::factory()->create(['is_internal' => true, 'logo_url' => 'https://esempio.test/casa.png']);
        $opponent = Team::factory()->create(['is_internal' => false, 'logo_url' => 'https://esempio.test/ospite.png']);

        Game::factory()->create([
            'home_team_id' => $internal->id,
            'away_team_id' => $opponent->id,
            'match_date' => now()->addDay(),
            'status' => GameStatus::Scheduled,
        ]);

        $nextGame = $this->nextGameProp();

        $this->assertNotNull($nextGame);
        $this->assertSame('https://esempio.test/casa.png', $nextGame['home_team']['logo_url']);
        $this->assertSame('https://esempio.test/ospite.png', $nextGame['away_team']['logo_url']);
    }

    public function test_no_next_match_is_shown_when_the_club_has_none(): void
    {
        $opponentA = Team::factory()->create(['is_internal' => false]);
        $opponentB = Team::factory()->create(['is_internal' => false]);

        Game::factory()->create([
            'home_team_id' => $opponentA->id,
            'away_team_id' => $opponentB->id,
            'match_date' => now()->addDays(3),
            'status' => GameStatus::Scheduled,
        ]);

        $this->assertNull($this->nextGameProp());
    }
}

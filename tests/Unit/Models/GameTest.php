<?php
namespace Tests\Unit\Models;

use App\Enums\GameStatus;
use App\Enums\CompetitionType;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_belongs_to_season(): void
    {
        $game = Game::factory()->create();

        $this->assertInstanceOf(Season::class, $game->season);
    }

    public function test_game_has_home_and_away_teams(): void
    {
        $home = Team::factory()->create();
        $away = Team::factory()->create();
        $game = Game::factory()->create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
        ]);

        $this->assertInstanceOf(Team::class, $game->homeTeam);
        $this->assertInstanceOf(Team::class, $game->awayTeam);
        $this->assertEquals($home->id, $game->homeTeam->id);
        $this->assertEquals($away->id, $game->awayTeam->id);
    }

    public function test_match_date_is_cast_to_datetime(): void
    {
        $game = Game::factory()->create(['match_date' => '2025-03-15 20:30:00']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $game->match_date);
    }

    public function test_status_is_cast_to_enum(): void
    {
        $game = Game::factory()->create();

        $this->assertInstanceOf(GameStatus::class, $game->status);
    }

    public function test_competition_type_is_cast_to_enum(): void
    {
        $game = Game::factory()->create();

        $this->assertInstanceOf(CompetitionType::class, $game->competition_type);
    }
}

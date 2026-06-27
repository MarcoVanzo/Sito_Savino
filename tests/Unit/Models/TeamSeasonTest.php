<?php
namespace Tests\Unit\Models;

use App\Models\Game;
use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamSeasonTest extends TestCase
{
    use RefreshDatabase;

    // --- Team ---

    public function test_team_has_many_rosters(): void
    {
        $team = Team::factory()->create();
        Roster::factory()->create(['team_id' => $team->id]);

        $this->assertCount(1, $team->rosters);
    }

    public function test_team_has_home_and_away_games(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $season = Season::factory()->create();

        Game::factory()->create([
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'season_id' => $season->id,
        ]);
        Game::factory()->create([
            'home_team_id' => $opponent->id,
            'away_team_id' => $team->id,
            'season_id' => $season->id,
        ]);

        $this->assertCount(1, $team->homeGames);
        $this->assertCount(1, $team->awayGames);
    }

    public function test_team_uses_soft_deletes(): void
    {
        $team = Team::factory()->create();
        $team->delete();

        $this->assertSoftDeleted($team);
    }

    public function test_team_is_internal_is_boolean(): void
    {
        $team = Team::factory()->create(['is_internal' => 1]);

        $this->assertTrue($team->is_internal);
    }

    // --- Season ---

    public function test_season_current_scope(): void
    {
        Season::factory()->create(['is_current' => true]);
        Season::factory()->create(['is_current' => false]);

        $this->assertCount(1, Season::current()->get());
    }

    public function test_season_has_many_rosters(): void
    {
        $season = Season::factory()->create();
        Roster::factory()->create(['season_id' => $season->id]);

        $this->assertCount(1, $season->rosters);
    }

    public function test_season_has_many_games(): void
    {
        $season = Season::factory()->create();
        Game::factory()->create(['season_id' => $season->id]);

        $this->assertCount(1, $season->games);
    }

    public function test_season_uses_soft_deletes(): void
    {
        $season = Season::factory()->create();
        $season->delete();

        $this->assertSoftDeleted($season);
    }

    // --- Roster ---

    public function test_roster_belongs_to_player_team_season(): void
    {
        $roster = Roster::factory()->create();

        $this->assertInstanceOf(Player::class, $roster->player);
        $this->assertInstanceOf(Team::class, $roster->team);
        $this->assertInstanceOf(Season::class, $roster->season);
    }

    public function test_roster_casts_is_captain_to_boolean(): void
    {
        $roster = Roster::factory()->create(['is_captain' => 1]);

        $this->assertTrue($roster->is_captain);
    }
}

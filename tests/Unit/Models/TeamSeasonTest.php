<?php

namespace Tests\Unit\Models;

use App\Models\Game;
use App\Models\Player;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamSeasonTest extends TestCase
{
    use RefreshDatabase;

    // --- Team ---

    #[Test]
    public function team_has_many_rosters(): void
    {
        $team = Team::factory()->create();
        Roster::factory()->create(['team_id' => $team->id]);

        $this->assertCount(1, $team->rosters);
    }

    #[Test]
    public function team_has_home_and_away_games(): void
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

    #[Test]
    public function team_uses_soft_deletes(): void
    {
        $team = Team::factory()->create();
        $team->delete();

        $this->assertSoftDeleted($team);
    }

    #[Test]
    public function team_is_internal_is_boolean(): void
    {
        $team = Team::factory()->create(['is_internal' => 1]);

        $this->assertTrue($team->is_internal);
    }

    // --- Season ---

    #[Test]
    public function season_current_scope(): void
    {
        Season::factory()->create(['is_current' => true]);
        Season::factory()->create(['is_current' => false]);

        $this->assertCount(1, Season::current()->get());
    }

    #[Test]
    public function season_has_many_rosters(): void
    {
        $season = Season::factory()->create();
        Roster::factory()->create(['season_id' => $season->id]);

        $this->assertCount(1, $season->rosters);
    }

    #[Test]
    public function season_has_many_games(): void
    {
        $season = Season::factory()->create();
        Game::factory()->create(['season_id' => $season->id]);

        $this->assertCount(1, $season->games);
    }

    #[Test]
    public function season_uses_soft_deletes(): void
    {
        $season = Season::factory()->create();
        $season->delete();

        $this->assertSoftDeleted($season);
    }

    // --- Roster ---

    #[Test]
    public function roster_belongs_to_player_team_season(): void
    {
        $roster = Roster::factory()->create();

        $this->assertInstanceOf(Player::class, $roster->player);
        $this->assertInstanceOf(Team::class, $roster->team);
        $this->assertInstanceOf(Season::class, $roster->season);
    }

    #[Test]
    public function roster_casts_is_captain_to_boolean(): void
    {
        $roster = Roster::factory()->create(['is_captain' => 1]);

        $this->assertTrue($roster->is_captain);
    }
}

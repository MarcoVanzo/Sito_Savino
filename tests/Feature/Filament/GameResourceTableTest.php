<?php

namespace Tests\Feature\Filament;

use App\Enums\GameStatus;
use App\Enums\UserRole;
use App\Filament\Resources\GameResource\Pages\ListGames;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameResourceTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_games_table_renders_with_every_status(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();
        $season = Season::factory()->create();

        foreach (GameStatus::cases() as $status) {
            Game::factory()->create([
                'season_id' => $season->id,
                'home_team_id' => Team::factory()->create()->id,
                'away_team_id' => Team::factory()->create()->id,
                'status' => $status,
            ]);
        }

        Livewire::actingAs($user)
            ->test(ListGames::class)
            ->assertSuccessful();
    }
}

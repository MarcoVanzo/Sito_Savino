<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Widgets\NextMatchWidget;
use App\Filament\Widgets\RecentActivityWidget;
use App\Models\ActivityLog;
use App\Models\Game;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * I widget della dashboard non devono mettere modelli Eloquent in cache:
     * alla rilettura dalla cache su database diventano __PHP_Incomplete_Class
     * e mandano in 500 l'intera dashboard. Il secondo render legge dalla cache.
     */
    public function test_next_match_widget_renders_twice_with_warm_cache(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        $season = Season::factory()->create();
        Game::factory()->create([
            'season_id' => $season->id,
            'home_team_id' => Team::factory()->create()->id,
            'away_team_id' => Team::factory()->create()->id,
            'match_date' => now()->addDays(3),
        ]);

        Livewire::actingAs($user)->test(NextMatchWidget::class)->assertSuccessful();
        Livewire::actingAs($user)->test(NextMatchWidget::class)->assertSuccessful();
    }

    public function test_next_match_widget_renders_without_upcoming_games(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        Livewire::actingAs($user)->test(NextMatchWidget::class)->assertSuccessful();
    }

    public function test_recent_activity_widget_renders_twice_with_warm_cache(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'created',
            'model_type' => User::class,
            'model_id' => $user->id,
            'model_label' => 'Test',
        ]);

        Livewire::actingAs($user)->test(RecentActivityWidget::class)->assertSuccessful();
        Livewire::actingAs($user)->test(RecentActivityWidget::class)->assertSuccessful();
    }
}

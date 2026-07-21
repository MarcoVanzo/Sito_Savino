<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\SeasonResource\Pages\EditSeason;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeasonResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user;
    }

    public function test_marking_a_season_as_current_demotes_the_others_on_save(): void
    {
        $old = Season::factory()->create(['is_current' => true]);
        $new = Season::factory()->create(['is_current' => false]);

        Livewire::actingAs($this->admin())
            ->test(EditSeason::class, ['record' => $new->getKey()])
            ->fillForm(['is_current' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($new->refresh()->is_current);
        $this->assertFalse($old->refresh()->is_current);
    }

    public function test_toggling_without_saving_does_not_demote_the_current_season(): void
    {
        $old = Season::factory()->create(['is_current' => true]);
        $new = Season::factory()->create(['is_current' => false]);

        Livewire::actingAs($this->admin())
            ->test(EditSeason::class, ['record' => $new->getKey()])
            ->fillForm(['is_current' => true]);

        // Nessun salvataggio: la stagione corrente deve restare quella di prima.
        $this->assertTrue($old->refresh()->is_current);
        $this->assertFalse($new->refresh()->is_current);
    }
}

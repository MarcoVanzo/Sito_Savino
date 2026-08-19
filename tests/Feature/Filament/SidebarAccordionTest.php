<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarAccordionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        return $user;
    }

    /**
     * I gruppi del menu partono chiusi: Filament scrive in localStorage la lista
     * dei gruppi collassati solo al primo accesso, leggendola dallo stato dei
     * NavigationGroup del pannello.
     */
    public function test_all_navigation_groups_start_collapsed(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->assertSuccessful()->getContent();

        foreach (['Stagione', 'Ticketing', 'Amministrazione'] as $group) {
            $this->assertStringContainsString(
                "\\u0022{$group}\\u0022",
                $html,
                "Il gruppo {$group} non risulta fra quelli collassati di default."
            );
        }
    }

    /**
     * Lo script che rende il menu a fisarmonica (aprendo un gruppo si chiudono
     * gli altri) deve essere iniettato nel pannello.
     */
    public function test_accordion_script_is_rendered(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('accordionPatched', false)
            ->assertSee('toggleCollapsedGroup', false);
    }
}

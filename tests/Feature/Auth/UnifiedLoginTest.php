<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_filament_login_page_redirects_to_site_login(): void
    {
        // Il pannello non mostra una pagina di login separata: rimanda a quella
        // brandizzata del sito.
        $response = $this->get('/admin/login');

        $response->assertRedirect(route('login'));
    }

    public function test_guest_visiting_admin_is_redirected_to_site_login(): void
    {
        // Filament rimanda il guest a /admin/login, che a sua volta reindirizza
        // alla pagina di login del sito: seguendo i redirect si arriva lì.
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');

        $this->get('/admin/login')->assertRedirect(route('login'));
    }

    public function test_staff_login_lands_on_admin_panel(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => false,
        ])->save();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user->fresh());
        $response->assertRedirect('/admin');
    }

    public function test_customer_login_lands_on_shop(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::Customer])->save();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user->fresh());
        $response->assertRedirect(route('shop', absolute: false));
    }
}

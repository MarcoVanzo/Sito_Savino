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

    public function test_filament_login_page_is_served_by_the_panel(): void
    {
        // Lo staff accede al CMS dalla login nativa di Filament, non dalla
        // pagina shop del sito.
        $this->get('/admin/login')->assertOk();
    }

    public function test_guest_visiting_admin_is_sent_to_filament_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_filament_password_reset_is_available(): void
    {
        // Il reset password dello staff segue lo stesso percorso del login CMS.
        $this->get('/admin/password-reset/request')->assertOk();
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

    public function test_staff_login_from_inertia_forces_a_full_page_visit_to_the_panel(): void
    {
        // Il pannello Filament non è una pagina Inertia: la risposta deve essere
        // 409 + X-Inertia-Location, altrimenti il client Inertia riceverebbe HTML
        // dove attende JSON e mostrerebbe il modale d'errore con il pannello
        // sovrapposto alla pagina di login.
        $user = User::factory()->create();
        $user->forceFill([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => false,
        ])->save();

        $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', '/admin');
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

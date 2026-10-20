<?php

namespace Tests\Feature\Shop;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\TestiDelleInformative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function registration_page_returns_200(): void
    {
        $response = $this->get(route('shop.register'));
        $response->assertStatus(200);
    }

    #[Test]
    public function registration_creates_user(): void
    {
        $response = $this->post(route('shop.register.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'mario@example.com',
            'role' => UserRole::Customer->value,
        ]);
    }

    #[Test]
    public function il_cliente_registrato_non_avvisa_i_super_admin_come_utente_in_attesa(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        $this->post(route('shop.register.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'mario@example.com', 'is_active' => true]);
        $this->assertSame(0, $admin->notifications()->count());
    }

    #[Test]
    public function registration_validates_required_fields(): void
    {
        $response = $this->post(route('shop.register.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $response->assertSessionDoesntHaveErrors('privacy_accepted');
    }

    /**
     * L'informativa si legge, non si accetta: nessuna casella da spuntare, ma
     * l'account ricorda quale versione era linkata nel modulo.
     */
    #[Test]
    public function la_registrazione_senza_casella_privacy_ricorda_la_versione_dell_informativa(): void
    {
        $this->post(route('shop.register.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            TestiDelleInformative::versione('privacy-policy'),
            User::where('email', 'mario@example.com')->value('informativa_privacy_versione'),
        );
    }

    #[Test]
    public function registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('shop.register.store'), [
            'name' => 'Duplicato',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function authenticated_user_cannot_access_registration(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('shop.register'));
        $response->assertRedirect();
    }
}

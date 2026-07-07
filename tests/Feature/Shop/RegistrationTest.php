<?php

namespace Tests\Feature\Shop;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_registration_page_returns_200(): void
    {
        $response = $this->get(route('shop.register'));
        $response->assertStatus(200);
    }

    public function test_registration_creates_user(): void
    {
        $response = $this->post(route('shop.register.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'privacy_accepted' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'mario@example.com',
            'role' => UserRole::Customer->value,
        ]);
    }

    public function test_registration_validates_required_fields(): void
    {
        $response = $this->post(route('shop.register.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'privacy_accepted']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('shop.register.store'), [
            'name' => 'Duplicato',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'privacy_accepted' => true,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_cannot_access_registration(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('shop.register'));
        $response->assertRedirect();
    }
}

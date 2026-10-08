<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function active_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        // is_active is set to true by factory afterCreating, no need to change

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    #[Test]
    public function inactive_user_is_redirected_to_login(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => false])->save();
        $user->refresh();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function inactive_user_is_logged_out(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => false])->save();
        $user->refresh();

        $this->actingAs($user)->get('/dashboard');

        $this->assertGuest();
    }

    #[Test]
    public function inactive_user_sees_error_message(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => false])->save();
        $user->refresh();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function active_user_can_access_profile(): void
    {
        $user = User::factory()->create();
        // is_active is set to true by factory afterCreating, no need to change

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
    }

    #[Test]
    public function inactive_user_cannot_access_profile(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_active' => false])->save();
        $user->refresh();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertRedirect(route('login'));
    }
}

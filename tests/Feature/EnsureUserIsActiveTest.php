<?php
namespace Tests\Feature;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_active_user_can_access_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_inactive_user_is_redirected_to_login(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_inactive_user_is_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/dashboard');

        $this->assertGuest();
    }

    public function test_inactive_user_sees_error_message(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSessionHasErrors('email');
    }

    public function test_active_user_can_access_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertStatus(200);
    }

    public function test_inactive_user_cannot_access_profile(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertRedirect(route('login'));
    }
}

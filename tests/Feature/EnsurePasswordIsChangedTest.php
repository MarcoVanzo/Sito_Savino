<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnsurePasswordIsChangedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_user_without_password_change_force_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => false])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_user_with_password_change_force_is_redirected_to_change_password_page(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('password.change'));
    }

    public function test_new_admin_user_is_forced_to_change_password(): void
    {
        $admin = User::create([
            'name' => 'Nuovo Admin',
            'email' => 'nuovo.admin@savinodelbene.it',
            'password' => 'temp_password123',
            'role' => UserRole::CommunicationManager,
            'is_active' => true,
        ]);

        $this->assertTrue($admin->must_change_password);
    }

    public function test_new_non_admin_user_is_not_forced_to_change_password(): void
    {
        $customer = User::create([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'password' => 'temp_password123',
            'role' => UserRole::Customer,
            'is_active' => true,
        ]);

        $this->assertFalse((bool) $customer->fresh()->must_change_password);
    }

    public function test_admin_with_password_change_force_is_redirected_from_admin_panel(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => true,
        ])->save();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect(route('password.change'));
    }

    public function test_user_with_password_change_force_can_access_change_password_page(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        $response = $this->actingAs($user)->get(route('password.change'));

        $response->assertStatus(200);
    }

    public function test_user_with_password_change_force_can_logout(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_user_cannot_change_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('temp_password'),
        ]);
        $user->forceFill(['must_change_password' => true])->save();

        $response = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'wrong_password',
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_user_cannot_change_password_to_the_same_password(): void
    {
        $user = User::factory()->create([
            'password' => 'temp_password123', // Model hashashed cast, but let's test
        ]);
        $user->forceFill(['must_change_password' => true])->save();

        $response = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'temp_password123',
            'password' => 'temp_password123',
            'password_confirmation' => 'temp_password123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_user_can_successfully_change_password(): void
    {
        $user = User::factory()->create([
            'password' => 'temp_password123',
            'role' => UserRole::User,
        ]);
        $user->forceFill(['must_change_password' => true])->save();

        $response = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'temp_password123',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->password));
    }

    public function test_admin_user_is_redirected_to_admin_after_password_change(): void
    {
        $user = User::factory()->create([
            'password' => 'temp_password123',
        ]);
        $user->forceFill([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => true,
        ])->save();

        $response = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'temp_password123',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertRedirect('/admin');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
    }

    public function test_admin_stays_authenticated_on_filament_panel_after_password_change(): void
    {
        // Riproduce il bug: dopo il cambio password forzato il pannello Filament
        // (middleware AuthenticateSession) non deve slogare l'utente per hash
        // password non aggiornato in sessione.
        $user = User::factory()->create([
            'password' => 'temp_password123',
        ]);
        $user->forceFill([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => true,
        ])->save();

        // Il session hash viene inizializzato dal middleware AuthenticateSession
        // al primo passaggio nel pannello (come avviene dopo un login reale).
        $this->actingAs($user)->get('/admin');

        $changeResponse = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'temp_password123',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $changeResponse->assertRedirect('/admin');

        // La sessione deve contenere l'hash aggiornato, così AuthenticateSession
        // non forza il logout al successivo accesso al pannello.
        $this->assertSame(
            $user->fresh()->getAuthPassword(),
            session('password_hash_'.Auth::getDefaultDriver()),
        );

        // Seguendo il redirect l'utente resta autenticato sul pannello e non
        // viene rimbalzato alla pagina di login di Filament.
        $panelResponse = $this->get('/admin');
        $panelResponse->assertStatus(200);
        $this->assertAuthenticatedAs($user->fresh());
    }
}

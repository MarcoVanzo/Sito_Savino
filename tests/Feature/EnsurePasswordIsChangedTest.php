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
        // `role` e `is_active` non sono assegnabili in massa: vanno impostati
        // prima del salvataggio, perché è il ruolo a decidere se il cambio
        // password al primo accesso è obbligatorio.
        $admin = new User([
            'name' => 'Nuovo Admin',
            'email' => 'nuovo.admin@savinodelbene.it',
            'password' => 'temp_password123',
        ]);

        $admin->forceFill([
            'role' => UserRole::CommunicationManager,
            'is_active' => true,
        ])->save();

        $this->assertTrue($admin->must_change_password);
    }

    public function test_new_non_admin_user_is_not_forced_to_change_password(): void
    {
        $customer = new User([
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
            'password' => 'temp_password123',
        ]);

        $customer->forceFill([
            'role' => UserRole::Customer,
            'is_active' => true,
        ])->save();

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

        // La sessione deve portare l'hash aggiornato, così AuthenticateSession
        // non forza il logout al successivo accesso al pannello. Si verifica il
        // comportamento e non il valore: il formato è interno al middleware
        // (hashPasswordForCookie) e non coincide con l'hash bcrypt dell'utente.
        $sessionHash = session('password_hash_'.Auth::getDefaultDriver());
        $this->assertNotNull($sessionHash);

        // Il difetto corretto era proprio scrivere qui l'hash bcrypt grezzo:
        // AuthenticateSession non lo riconosce e al passaggio successivo
        // sloggava l'utente. Asserire solo "non nullo" non lo intercetterebbe.
        $this->assertNotSame(
            $user->fresh()->getAuthPassword(),
            $sessionHash,
            "In sessione è finito l'hash bcrypt grezzo invece del formato di AuthenticateSession."
        );

        // Seguendo il redirect l'utente resta autenticato sul pannello e non
        // viene rimbalzato alla pagina di login di Filament.
        $panelResponse = $this->get('/admin');
        $panelResponse->assertStatus(200);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_json_accept_header_does_not_bypass_forced_password_change(): void
    {
        // Regressione: il middleware saltava il controllo su expectsJson(), che
        // dipende solo dall'header Accept inviato dal client. Bastava quindi
        // chiedere JSON per navigare il sito senza cambiare la password.
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/dashboard');

        $response->assertRedirect(route('password.change'));
    }

    public function test_a_path_containing_logout_does_not_bypass_forced_password_change(): void
    {
        // `Str::contains($path, 'logout')` lasciava passare qualsiasi URL con
        // "logout" al suo interno (per esempio uno slug di pagina o prodotto).
        $user = User::factory()->create();
        $user->forceFill(['must_change_password' => true])->save();

        $response = $this->actingAs($user)->get('/logout-pagina-inesistente');

        $response->assertRedirect(route('password.change'));
    }

    public function test_force_change_password_from_inertia_forces_full_page_visit_to_panel(): void
    {
        // Regressione: il redirect finale punta al pannello Filament, che non è
        // una pagina Inertia. Senza Inertia::location il client mostrava il
        // modale d'errore con la dashboard sovrapposta al form di cambio password.
        $user = User::factory()->create();
        $user->forceFill([
            'role' => UserRole::SuperAdmin,
            'must_change_password' => true,
        ])->save();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
            ->post('/change-password', [
                'current_password' => 'password',
                'password' => 'NuovaPassword!2026',
                'password_confirmation' => 'NuovaPassword!2026',
            ]);

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', '/admin');
        $this->assertFalse((bool) $user->fresh()->must_change_password);
    }
}

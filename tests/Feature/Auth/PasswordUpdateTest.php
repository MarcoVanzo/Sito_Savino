<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'PasswordSicura!2026',
                'password_confirmation' => 'PasswordSicura!2026',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('PasswordSicura!2026', $user->refresh()->password));
    }

    #[Test]
    public function current_session_survives_the_password_change(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'PasswordSicura!2026',
                'password_confirmation' => 'PasswordSicura!2026',
            ])
            ->assertSessionHasNoErrors();

        // AuthenticateSession sul gruppo web invalida le ALTRE sessioni al
        // cambio password: quella corrente deve restare valida, altrimenti
        // l'utente viene sbattuto fuori appena cambia la propria password.
        $this->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    #[Test]
    public function a_session_with_a_stale_password_hash_is_logged_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();

        // Simula una seconda sessione rimasta all'hash precedente: e' cio' che
        // accade a un dispositivo gia' loggato dopo un cambio password altrove.
        session(['password_hash_web' => 'hash-non-piu-valido']);

        $this->get('/profile')->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'PasswordSicura!2026',
                'password_confirmation' => 'PasswordSicura!2026',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');
    }
}

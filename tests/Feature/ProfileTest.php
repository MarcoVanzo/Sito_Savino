<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    #[Test]
    public function profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    /**
     * Cambiare l'email è il primo passo per prendersi un account (poi basta
     * il reset della password): con una sessione lasciata aperta non deve
     * bastare, serve la password attuale.
     */
    #[Test]
    public function per_cambiare_l_email_serve_la_password_attuale(): void
    {
        $user = User::factory()->create(['email' => 'mia@example.com']);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', ['name' => 'Test User', 'email' => 'altrui@example.com'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', ['name' => 'Test User', 'email' => 'altrui@example.com', 'current_password' => 'sbagliata'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('mia@example.com', $user->refresh()->email);
    }

    #[Test]
    public function per_cambiare_solo_il_nome_la_password_non_serve(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', ['name' => 'Nome Nuovo', 'email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('Nome Nuovo', $user->refresh()->name);
    }

    #[Test]
    public function email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    #[Test]
    public function user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    #[Test]
    public function un_redattore_non_si_cancella_nemmeno_da_profile(): void
    {
        // /profile faceva $user->delete() senza le regole di /shop/account.
        $redattore = User::factory()->create();
        $redattore->forceFill(['role' => UserRole::CommunicationManager])->save();

        $this->actingAs($redattore)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertModelExists($redattore);
    }

    #[Test]
    public function la_cancellazione_da_profile_lascia_il_recapito_sugli_ordini(): void
    {
        $user = User::factory()->create();
        $ordine = Order::factory()->create(['user_id' => $user->id, 'guest_email' => null]);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertSame($user->email, $ordine->fresh()->guest_email);
    }

    #[Test]
    public function correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}

<?php

namespace Tests\Feature\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La registrazione dello shop deve applicare la stessa policy password
 * del resto del sito (Password::defaults(): 12 caratteri, maiuscole/minuscole,
 * numeri e simboli).
 */
class ShopRegistrationPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_registration_rejects_weak_password(): void
    {
        $response = $this->post(route('shop.register.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => 'Pass123!',
            'password_confirmation' => 'Pass123!',
            'privacy_accepted' => true,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'mario@example.com']);
    }

    public function test_shop_registration_accepts_compliant_password(): void
    {
        $response = $this->post(route('shop.register.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario2@example.com',
            'password' => 'PasswordSicura1!',
            'password_confirmation' => 'PasswordSicura1!',
            'privacy_accepted' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNotNull(User::where('email', 'mario2@example.com')->first());
    }
}

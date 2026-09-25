<?php

namespace Tests\Feature\Shop;

use App\Enums\UserRole;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il cliente trova come scaricare i propri dati e come cancellarsi (articoli
 * 17 e 20 del GDPR). Prima la cancellazione esisteva solo nella pagina di
 * Breeze sotto `/profile`, che nessun link del sito raggiungeva.
 */
class AccountClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_la_pagina_dell_account_richiede_l_accesso(): void
    {
        $this->get(route('shop.account'))->assertRedirect();
    }

    public function test_il_cliente_vede_il_suo_account(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($cliente)
            ->get(route('shop.account'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Shop/Account')
                ->where('account.email', $cliente->email)
                ->where('motivoPerNonCancellare', null));
    }

    public function test_l_esportazione_contiene_account_ordini_e_newsletter(): void
    {
        $cliente = $this->cliente();
        $ordine = Order::factory()->create(['user_id' => $cliente->id]);
        NewsletterSubscriber::factory()->create(['email' => $cliente->email]);

        $risposta = $this->actingAs($cliente)->get(route('shop.account.export'));

        $risposta->assertOk();
        $this->assertStringContainsString('attachment;', $risposta->headers->get('Content-Disposition'));
        $risposta->assertJsonPath('account.email', $cliente->email);
        $risposta->assertJsonPath('ordini.0.numero', $ordine->order_number);
        $risposta->assertJsonPath('newsletter.email', $cliente->email);
        $this->assertStringNotContainsString('password', strtolower($risposta->getContent()));
    }

    public function test_la_cancellazione_chiede_la_password(): void
    {
        $cliente = $this->cliente();

        $this->actingAs($cliente)
            ->delete(route('shop.account.destroy'), ['password' => 'sbagliata'])
            ->assertSessionHasErrors('password');

        $this->assertModelExists($cliente);
    }

    public function test_cancellato_l_account_gli_ordini_restano_senza_legame(): void
    {
        $cliente = $this->cliente();
        $ordine = Order::factory()->create(['user_id' => $cliente->id]);

        $this->actingAs($cliente)
            ->delete(route('shop.account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('shop'));

        $this->assertModelMissing($cliente);
        $this->assertGuest();
        $this->assertNull($ordine->fresh()->user_id);
    }

    public function test_un_account_della_redazione_non_si_cancella_da_qui(): void
    {
        $redattore = User::factory()->create();
        $redattore->forceFill(['role' => UserRole::CommunicationManager])->save();

        $this->actingAs($redattore)
            ->delete(route('shop.account.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertModelExists($redattore);
    }

    private function cliente(): User
    {
        $cliente = User::factory()->create();
        $cliente->forceFill(['role' => UserRole::Customer])->save();

        return $cliente;
    }
}

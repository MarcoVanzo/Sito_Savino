<?php

namespace Tests\Feature\Shop;

use App\Enums\AuctionStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\RichiestaDiRecesso;
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

    public function test_l_esportazione_contiene_recessi_e_messaggi_con_lo_stesso_indirizzo(): void
    {
        $cliente = $this->cliente();
        RichiestaDiRecesso::create([
            'numero_ordine' => 'SDB-1', 'nome' => $cliente->name, 'email' => $cliente->email,
            'lingua' => 'it', 'inviata_il' => now(),
        ]);
        ContactMessage::create(['name' => $cliente->name, 'email' => $cliente->email, 'subject' => 'Domanda', 'message' => 'Testo']);

        $this->actingAs($cliente)->get(route('shop.account.export'))
            ->assertOk()
            ->assertJsonPath('dichiarazioni_di_recesso.0.ordine', 'SDB-1')
            ->assertJsonPath('messaggi.0.oggetto', 'Domanda');
    }

    public function test_chi_puo_ancora_ricevere_un_asta_non_pagata_non_si_cancella(): void
    {
        // Il secondo in classifica di un'asta chiusa e non pagata può ancora
        // riceverla: cancellandosi, l'asta finiva a un vincitore nullo che
        // nessun giro rivedeva più.
        $vincitore = $this->cliente();
        $secondo = $this->cliente();
        $asta = Auction::factory()->create([
            'status' => AuctionStatus::Ended,
            'winner_user_id' => $vincitore->id,
            'winner_checkout_deadline' => now()->subMinute(),
        ]);
        Bid::factory()->create(['auction_id' => $asta->id, 'user_id' => $vincitore->id, 'amount' => 200, 'is_valid' => true]);
        Bid::factory()->create(['auction_id' => $asta->id, 'user_id' => $secondo->id, 'amount' => 150, 'is_valid' => true]);

        foreach ([$vincitore, $secondo] as $utente) {
            $this->actingAs($utente)
                ->delete(route('shop.account.destroy'), ['password' => 'password'])
                ->assertSessionHasErrors('password');
            $this->assertModelExists($utente);
        }
    }

    public function test_cancellato_l_account_il_registro_non_ne_tiene_i_dati(): void
    {
        $cliente = $this->cliente();
        $cliente->update(['name' => 'Nome Riservato']);

        $this->actingAs($cliente)
            ->delete(route('shop.account.destroy'), ['password' => 'password'])
            ->assertRedirect();

        $righe = ActivityLog::where('model_type', User::class)->where('model_id', $cliente->id)->get();
        $this->assertNotEmpty($righe);
        foreach ($righe as $riga) {
            $this->assertNull($riga->changes);
            $this->assertStringNotContainsString('Riservato', (string) $riga->model_label);
        }
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

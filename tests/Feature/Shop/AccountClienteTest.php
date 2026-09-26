<?php

namespace Tests\Feature\Shop;

use App\Enums\AuctionStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\RichiestaDiRecesso;
use App\Models\User;
use App\Services\Payments\StripeCustomerService;
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

    public function test_cancellato_l_account_l_ordine_d_asta_conserva_il_recapito(): void
    {
        // L'ordine d'asta non valorizza guest_email/guest_name: dopo la
        // cancellazione non restava nessuno a cui mandare spedizione,
        // rimborso o la ricevuta di un recesso.
        $cliente = $this->cliente();
        $cliente->update(['name' => 'Anna Rossi']);
        $ordineAsta = Order::factory()->create(['user_id' => $cliente->id, 'guest_email' => null, 'guest_name' => null]);
        $ordineShop = Order::factory()->create(['user_id' => $cliente->id, 'guest_email' => 'altro@example.com', 'guest_name' => 'Nome al checkout']);

        $this->actingAs($cliente)
            ->delete(route('shop.account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('shop'));

        $ordineAsta->refresh();
        $this->assertNull($ordineAsta->user_id);
        $this->assertSame($cliente->email, $ordineAsta->guest_email);
        $this->assertSame('Anna Rossi', $ordineAsta->guest_name);

        $recesso = RichiestaDiRecesso::create([
            'numero_ordine' => $ordineAsta->order_number, 'nome' => 'Anna Rossi', 'email' => $cliente->email,
            'lingua' => 'it', 'inviata_il' => now(),
        ]);
        $recesso->forceFill(['order_id' => $ordineAsta->id])->save();
        $this->assertSame($cliente->email, $recesso->emailDellOrdine());

        // Quello che il cliente aveva scritto al checkout non si tocca.
        $ordineShop->refresh();
        $this->assertSame('altro@example.com', $ordineShop->guest_email);
        $this->assertSame('Nome al checkout', $ordineShop->guest_name);
    }

    public function test_l_esportazione_comprende_gli_ordini_da_ospite_se_l_email_e_verificata(): void
    {
        $cliente = $this->cliente();
        $daOspite = Order::factory()->create(['user_id' => null, 'guest_email' => $cliente->email]);
        $diUnAltro = Order::factory()->create(['user_id' => null, 'guest_email' => 'altro@example.com']);

        $numeri = collect($this->actingAs($cliente)->get(route('shop.account.export'))->assertOk()->json('ordini'))
            ->pluck('numero');

        $this->assertContains($daOspite->order_number, $numeri);
        $this->assertNotContains($diUnAltro->order_number, $numeri);
    }

    public function test_senza_email_verificata_gli_ordini_da_ospite_restano_fuori(): void
    {
        // Chi registra un account con l'email di un altro non deve scaricarne
        // indirizzi e codice fiscale.
        $cliente = $this->cliente();
        $cliente->forceFill(['email_verified_at' => null])->save();
        $daOspite = Order::factory()->create(['user_id' => null, 'guest_email' => $cliente->email]);

        $numeri = collect($this->actingAs($cliente)->get(route('shop.account.export'))->assertOk()->json('ordini'))
            ->pluck('numero');

        $this->assertNotContains($daOspite->order_number, $numeri);
    }

    public function test_l_esportazione_comprende_il_carrello(): void
    {
        $cliente = $this->cliente();
        $carrello = Cart::factory()->create(['user_id' => $cliente->id]);
        CartItem::factory()->create(['cart_id' => $carrello->id, 'quantity' => 2]);

        $this->actingAs($cliente)->get(route('shop.account.export'))
            ->assertOk()
            ->assertJsonPath('carrello.0.quantita', 2);
    }

    public function test_cancellato_l_account_si_cancella_anche_il_cliente_su_stripe(): void
    {
        config(['services.stripe.secret' => 'sk_test_finto']);
        $cliente = $this->cliente();
        $cliente->forceFill(['stripe_customer_id' => 'cus_prova'])->save();

        $stripe = $this->mock(StripeCustomerService::class);
        $stripe->shouldReceive('cancellaCustomer')->once()->with('cus_prova');

        $this->actingAs($cliente)
            ->delete(route('shop.account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('shop'));

        $this->assertModelMissing($cliente);
    }

    public function test_un_errore_di_stripe_non_blocca_la_cancellazione(): void
    {
        config(['services.stripe.secret' => 'sk_test_finto']);
        $cliente = $this->cliente();
        $cliente->forceFill(['stripe_customer_id' => 'cus_prova'])->save();

        $stripe = $this->mock(StripeCustomerService::class);
        $stripe->shouldReceive('cancellaCustomer')->once()->andThrow(new \RuntimeException('Stripe irraggiungibile'));

        $this->actingAs($cliente)
            ->delete(route('shop.account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('shop'));

        $this->assertModelMissing($cliente);
    }

    public function test_senza_chiavi_di_stripe_non_si_chiama_stripe(): void
    {
        config(['services.stripe.secret' => null]);
        $cliente = $this->cliente();
        $cliente->forceFill(['stripe_customer_id' => 'cus_prova'])->save();

        $stripe = $this->mock(StripeCustomerService::class);
        $stripe->shouldNotReceive('cancellaCustomer');

        $this->actingAs($cliente)
            ->delete(route('shop.account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('shop'));
    }

    private function cliente(): User
    {
        $cliente = User::factory()->create();
        $cliente->forceFill(['role' => UserRole::Customer])->save();

        return $cliente;
    }
}

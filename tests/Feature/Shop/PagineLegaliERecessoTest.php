<?php

namespace Tests\Feature\Shop;

use App\Mail\AvvisoDiRecessoAlloShop;
use App\Mail\AvvisoDiRecessoAlTitolare;
use App\Mail\RicevutaDiRecesso;
use App\Models\Order;
use App\Models\Page;
use App\Models\RichiestaDiRecesso;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\PagineLegaliDelloShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spedizioni, resi e regolamento aste (portati dal vecchio negozio
 * WooCommerce) e la funzione di recesso online (art. 54-bis del Codice del
 * Consumo): il recesso si esercita dal sito in due passaggi e produce subito
 * una ricevuta con data e ora. Condizioni di vendita e accettazione al
 * checkout stanno in CondizioniDiVenditaTest.
 */
class PagineLegaliERecessoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_pagine_legali_dello_shop_nascono_con_le_migrazioni(): void
    {
        foreach (array_keys(PagineLegaliDelloShop::tutte()) as $slug) {
            $pagina = Page::where('slug', $slug)->first();

            $this->assertNotNull($pagina, "Manca la pagina {$slug}");
            $this->assertNotEmpty($pagina->getTranslation('content', 'it', false));
            $this->assertNotEmpty($pagina->getTranslation('content', 'en', false));
        }
    }

    #[Test]
    public function una_pagina_gia_esistente_non_viene_riscritta(): void
    {
        Page::where('slug', PagineLegaliDelloShop::SPEDIZIONI)->first()
            ->setTranslation('content', 'it', '<p>Scritto dalla redazione</p>')->save();

        $this->assertSame([], PagineLegaliDelloShop::creaQuelleCheMancano());
        $this->assertSame(
            '<p>Scritto dalla redazione</p>',
            Page::where('slug', PagineLegaliDelloShop::SPEDIZIONI)->first()->getTranslation('content', 'it'),
        );
    }

    #[Test]
    public function la_pagina_spedizioni_riceve_le_zone_del_pannello(): void
    {
        ShippingZone::factory()->create([
            'countries' => ['IT'],
            'flat_rate' => 7.5,
            'free_threshold' => 100,
            'estimated_days_min' => 2,
            'estimated_days_max' => 7,
            'is_active' => true,
        ]);

        $this->get('/spedizioni')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->component('Public/ContentPage')
                ->has('zoneDiSpedizione', 1)
                ->where('zoneDiSpedizione.0.soglia_gratuita', 100)
                ->where('zoneDiSpedizione.0.giorni_max', 7));
    }

    #[Test]
    public function le_pagine_rispondono_anche_in_inglese(): void
    {
        $this->get('/condizioni-di-vendita')->assertOk();
        $this->get('/resi-e-rimborsi')->assertOk();
        $this->get('/en/condizioni-di-vendita')->assertOk();
    }

    #[Test]
    public function gli_indirizzi_del_vecchio_negozio_portano_alle_pagine_nuove(): void
    {
        $this->get('/condizioni-generali-di-vendita')->assertStatus(301)->assertRedirect('/condizioni-di-vendita');
        $this->get('/condizioni-di-vendita-del-negozio-online')->assertStatus(301)->assertRedirect('/condizioni-di-vendita');
        $this->get('/condizioni-di-spedizione')->assertStatus(301)->assertRedirect('/spedizioni');
        $this->get('/informativa-sui-rimborsi')->assertStatus(301)->assertRedirect('/resi-e-rimborsi');
        $this->get('/regolamento-e-privacy-policy-aste')->assertStatus(301)->assertRedirect('/regolamento-aste');
    }

    #[Test]
    public function l_elenco_delle_aste_mostra_il_regolamento_della_pagina(): void
    {
        $this->get(route('shop.auctions.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->where('rulesText', fn ($testo) => str_contains((string) $testo, 'prezzo di riserva')));
    }

    #[Test]
    public function la_funzione_di_recesso_si_apre_con_l_ordine_gia_scritto(): void
    {
        $this->get(route('recesso', ['ordine' => 'SDB-123']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->component('Public/Shop/Recesso')
                ->where('precompilato.numero_ordine', 'SDB-123')
                ->where('ricevuta', null));
    }

    #[Test]
    public function senza_la_conferma_il_recesso_non_parte(): void
    {
        $this->post(route('recesso.store'), [
            'nome' => 'Maria Bianchi',
            'email' => 'maria@example.com',
            'numero_ordine' => 'SDB-123',
        ])->assertSessionHasErrors('conferma');

        $this->assertSame(0, RichiestaDiRecesso::count());
    }

    #[Test]
    public function il_recesso_confermato_si_registra_e_manda_subito_la_ricevuta(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $ordine = Order::factory()->create(['user_id' => $user->id]);

        $risposta = $this->post(route('recesso.store'), [
            'nome' => 'Maria Bianchi',
            'email' => 'maria@example.com',
            'numero_ordine' => $ordine->order_number,
            'articoli' => 'Maglia home taglia M',
            'conferma' => true,
        ])->assertRedirect();

        // La ricevuta sta su un indirizzo firmato: ricaricarla la rilegge,
        // non rimanda la dichiarazione.
        $this->get($risposta->headers->get('Location'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->component('Public/Shop/Recesso')
                ->where('ricevuta.numero_ordine', $ordine->order_number)
                ->has('ricevuta.inviata_il'));
        $this->get(route('recesso.ricevuta', ['richiesta' => RichiestaDiRecesso::sole()->id]))->assertForbidden();

        $richiesta = RichiestaDiRecesso::sole();
        $this->assertSame($ordine->id, $richiesta->order_id);
        $this->assertNotNull($richiesta->inviata_il);

        // La ricevuta parte subito (send, non queue); l'avviso allo shop in coda.
        Mail::assertSent(RicevutaDiRecesso::class, fn ($mail) => $mail->hasTo('maria@example.com'));
        Mail::assertQueued(AvvisoDiRecessoAlloShop::class);

        // L'ordine è intestato a un altro indirizzo: il titolare riceve un
        // avviso (non la ricevuta), e il pannello lo segnala.
        Mail::assertSent(AvvisoDiRecessoAlTitolare::class, fn ($mail) => $mail->hasTo($user->email));
        Mail::assertNotSent(RicevutaDiRecesso::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertTrue($richiesta->emailDiversaDaQuellaDellOrdine());
    }

    #[Test]
    public function lo_stesso_indirizzo_non_riceve_piu_di_tre_ricevute_al_giorno_ma_la_dichiarazione_si_registra(): void
    {
        // Il limite per IP della rotta non ferma chi cambia indirizzo IP: la
        // ricevuta va all'email scritta nel modulo. Oltre la soglia si salta
        // l'email, non la dichiarazione: il diritto non ha un tetto.
        Mail::fake();

        foreach (range(1, 4) as $volta) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$volta])
                ->post(route('recesso.store'), [
                    'nome' => 'Chiunque',
                    'email' => 'vittima@example.com',
                    'numero_ordine' => 'X-'.$volta,
                    'conferma' => true,
                ]);
        }

        $this->assertSame(4, RichiestaDiRecesso::count());
        Mail::assertSentCount(3);
    }

    #[Test]
    public function il_recesso_si_accetta_anche_con_un_numero_d_ordine_sconosciuto(): void
    {
        Mail::fake();

        $this->post(route('recesso.store'), [
            'nome' => 'Maria Bianchi',
            'email' => 'maria@example.com',
            'numero_ordine' => 'NON-ESISTE',
            'conferma' => true,
        ])->assertRedirect();

        $this->assertNull(RichiestaDiRecesso::sole()->order_id);
    }

    #[Test]
    public function la_ricevuta_riporta_dichiarazione_data_e_ora(): void
    {
        $richiesta = RichiestaDiRecesso::create([
            'numero_ordine' => 'SDB-123',
            'nome' => 'Maria Bianchi',
            'email' => 'maria@example.com',
            'lingua' => 'it',
            'inviata_il' => now()->setDate(2026, 9, 25)->setTime(10, 15, 30),
        ]);

        $html = (new RicevutaDiRecesso($richiesta))->render();

        $this->assertStringContainsString('notifico il recesso dal contratto di vendita relativo all&#039;ordine SDB-123', $html);
        $this->assertStringContainsString('25/09/2026', $html);
    }

    #[Test]
    public function l_avviso_ue_sulla_garanzia_c_e_nelle_due_lingue(): void
    {
        // Le immagini sono la pagina a colori dei PDF ufficiali della
        // Commissione (Reg. di esecuzione UE 2025/1960): senza, la finestra
        // "I tuoi diritti di garanzia legale" si aprirebbe vuota.
        foreach (['it', 'en'] as $lingua) {
            $this->assertFileExists(public_path("images/garanzia/avviso-garanzia-legale-{$lingua}.png"));
        }
    }

    #[Test]
    public function rea_e_capitale_sociale_vengono_dalla_visura(): void
    {
        // Art. 2250 c.c.: il sito di una societa' di capitali indica REA e
        // capitale versato. Valori della visura camerale del 07/08/2025.
        $this->assertSame('FI-624279', SiteSetting::get('contact.legal_rea'));
        $this->assertSame('€ 150.000,00 i.v.', SiteSetting::get('contact.legal_capitale'));

        $condizioni = Page::where('slug', 'condizioni-di-vendita')->first()->getTranslation('content', 'it');
        $this->assertStringContainsString('REA FI-624279', $condizioni);
        $this->assertStringContainsString('150.000,00', $condizioni);
    }

    #[Test]
    public function troppi_invii_da_inertia_tornano_alla_pagina_con_un_messaggio(): void
    {
        // Un 429 grezzo a una richiesta Inertia apriva la finestra con l'HTML
        // di Laravel: ora si torna indietro con il modulo compilato.

        foreach (range(1, 3) as $volta) {
            $this->post(route('recesso.store'), []);
        }

        $this->from(route('recesso'))
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('recesso.store'), [])
            ->assertRedirect(route('recesso'))
            ->assertSessionHas('error', __('messages.errori.troppi_tentativi'));
    }
}

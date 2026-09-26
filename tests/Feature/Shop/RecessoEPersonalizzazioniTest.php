<?php

namespace Tests\Feature\Shop;

use App\Mail\AvvisoDiRecessoAlTitolare;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\RichiestaDiRecesso;
use App\Models\User;
use App\Support\CondizioniDiVendita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le righe personalizzate (la firma della giocatrice) sono escluse dal
 * recesso (art. 59 c. 1 lett. c del Codice del consumo): la funzione
 * `/recesso` non le lascia scegliere e il server le rifiuta. La funzione in
 * inglese ha uno slug inglese, con il 301 dal vecchio indirizzo. E il
 * titolare di un ordine altrui riceve un avviso senza i dati di chi scrive.
 */
class RecessoEPersonalizzazioniTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function con_il_token_dell_ordine_la_pagina_elenca_le_righe_e_segna_le_personalizzate(): void
    {
        [$ordine, $normale, $firmata] = $this->ordineConUnaRigaFirmata();

        $this->get(route('recesso', ['ordine' => $ordine->order_number, 'token' => $ordine->order_token]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina
                ->component('Public/Shop/Recesso')
                ->where('precompilato.token', $ordine->order_token)
                ->has('righe', 2)
                ->where('righe.0.id', $normale->id)
                ->where('righe.0.personalizzata', false)
                ->where('righe.1.id', $firmata->id)
                ->where('righe.1.personalizzata', true)
                ->where('righe.1.descrizione', fn ($testo) => str_contains((string) $testo, 'Firma della giocatrice')));
    }

    #[Test]
    public function senza_token_ne_account_le_righe_non_si_vedono(): void
    {
        [$ordine] = $this->ordineConUnaRigaFirmata();

        $this->get(route('recesso', ['ordine' => $ordine->order_number]))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('righe', null));

        $this->get(route('recesso', ['ordine' => $ordine->order_number, 'token' => 'sbagliato']))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->where('righe', null)->where('precompilato.token', ''));
    }

    #[Test]
    public function il_titolare_dell_account_vede_le_righe_senza_token(): void
    {
        $cliente = User::factory()->create();
        [$ordine] = $this->ordineConUnaRigaFirmata(['user_id' => $cliente->id]);

        $this->actingAs($cliente)
            ->get(route('recesso', ['ordine' => $ordine->order_number]))
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->has('righe', 2));
    }

    #[Test]
    public function il_server_rifiuta_una_riga_personalizzata(): void
    {
        Mail::fake();
        [$ordine, $normale, $firmata] = $this->ordineConUnaRigaFirmata();

        $this->post(route('recesso.store'), $this->dati($ordine, [$normale->id, $firmata->id]))
            ->assertSessionHasErrors(['righe' => __('messages.recesso.personalizzato_escluso')]);

        $this->assertSame(0, RichiestaDiRecesso::count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function le_righe_restituibili_diventano_il_testo_della_dichiarazione(): void
    {
        Mail::fake();
        [$ordine, $normale] = $this->ordineConUnaRigaFirmata();

        $this->post(route('recesso.store'), $this->dati($ordine, [$normale->id]))->assertRedirect();

        $richiesta = RichiestaDiRecesso::sole();
        $this->assertSame($ordine->id, $richiesta->order_id);
        $this->assertSame('1 × Maglia home', $richiesta->articoli);
    }

    #[Test]
    public function una_riga_di_un_altro_ordine_o_senza_token_non_passa(): void
    {
        [$ordine, $normale] = $this->ordineConUnaRigaFirmata();
        [, $altra] = $this->ordineConUnaRigaFirmata();

        $this->post(route('recesso.store'), $this->dati($ordine, [$altra->id]))
            ->assertSessionHasErrors(['righe' => __('messages.recesso.righe_non_valide')]);

        $this->post(route('recesso.store'), [...$this->dati($ordine, [$normale->id]), 'token' => 'sbagliato'])
            ->assertSessionHasErrors('righe');

        $this->post(route('recesso.store'), $this->dati($ordine, []))
            ->assertSessionHasErrors(['righe' => __('messages.recesso.nessuna_riga')]);

        $this->assertSame(0, RichiestaDiRecesso::count());
    }

    #[Test]
    public function l_avviso_al_titolare_non_contiene_i_dati_del_dichiarante(): void
    {
        Mail::fake();
        $titolare = User::factory()->create(['email' => 'titolare@example.com']);
        $ordine = Order::factory()->create(['user_id' => $titolare->id, 'locale' => 'en']);

        $this->post(route('recesso.store'), [
            'nome' => 'Nome Del Dichiarante',
            'email' => 'dichiarante@example.com',
            'numero_ordine' => $ordine->order_number,
            'articoli' => 'Testo scritto da chi compila',
            'conferma' => true,
        ])->assertRedirect();

        Mail::assertSent(AvvisoDiRecessoAlTitolare::class, function (AvvisoDiRecessoAlTitolare $mail) use ($ordine) {
            $html = $mail->render();

            $this->assertTrue($mail->hasTo('titolare@example.com'));
            $this->assertSame('en', $mail->locale);
            $this->assertStringContainsString($ordine->order_number, $html);
            $this->assertStringContainsString('info@savinodelbenevolley.it', $html);
            $this->assertStringNotContainsString('Nome Del Dichiarante', $html);
            $this->assertStringNotContainsString('dichiarante@example.com', $html);
            $this->assertStringNotContainsString('Testo scritto da chi compila', $html);

            return true;
        });
    }

    #[Test]
    public function al_titolare_arriva_al_massimo_un_avviso_al_giorno(): void
    {
        Mail::fake();
        $titolare = User::factory()->create();
        $ordine = Order::factory()->create(['user_id' => $titolare->id]);

        foreach (['a', 'b', 'c'] as $chi) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.'.ord($chi)])
                ->post(route('recesso.store'), [
                    'nome' => 'Dichiarante '.$chi,
                    'email' => $chi.'@example.com',
                    'numero_ordine' => $ordine->order_number,
                    'conferma' => true,
                ])->assertRedirect();
        }

        $this->assertSame(3, RichiestaDiRecesso::count());
        Mail::assertSent(AvvisoDiRecessoAlTitolare::class, 1);
    }

    #[Test]
    public function in_inglese_la_funzione_sta_su_withdrawal_e_il_vecchio_indirizzo_fa_301(): void
    {
        $this->assertStringEndsWith('/en/withdrawal', route('en.recesso'));
        $this->get('/en/withdrawal')->assertOk();

        $this->get('/en/recesso?ordine=SDB-1&token=abc')
            ->assertStatus(301)
            ->assertRedirect(route('en.recesso', ['ordine' => 'SDB-1', 'token' => 'abc']));

        // L'italiano non cambia.
        $this->assertStringEndsWith('/recesso', route('recesso'));
    }

    #[Test]
    public function l_email_di_conferma_porta_al_recesso_nella_lingua_dell_ordine_col_token(): void
    {
        $ordine = Order::factory()->create(['locale' => 'en', 'condizioni_versione' => CondizioniDiVendita::VERSIONE]);

        (new OrderConfirmation($ordine))->assertSeeInHtml(
            e(route('en.recesso', ['ordine' => $ordine->order_number, 'token' => $ordine->order_token])),
            false,
        );
    }

    #[Test]
    public function l_email_segna_la_riga_personalizzata_come_esclusa(): void
    {
        [$ordine] = $this->ordineConUnaRigaFirmata(['locale' => 'it', 'condizioni_versione' => CondizioniDiVendita::VERSIONE]);

        (new OrderConfirmation($ordine->fresh()))->assertSeeInHtml('Personalizzato: escluso dal diritto di recesso');
    }

    #[Test]
    public function le_pagine_del_recesso_nominano_la_firma_e_i_link_inglesi_vanno_a_withdrawal(): void
    {
        foreach ([CondizioniDiVendita::SLUG_CONDIZIONI, CondizioniDiVendita::SLUG_RECESSO] as $slug) {
            $testo = CondizioniDiVendita::contenuto($slug);
            $this->assertStringContainsString('la firma di una giocatrice', $testo['it']);
            $this->assertStringContainsString("a player's signature", $testo['en']);
        }

        $this->assertStringNotContainsString('/en/recesso', (string) json_encode(CondizioniDiVendita::contenuto(CondizioniDiVendita::SLUG_RECESSO)));
    }

    #[Test]
    public function la_migrazione_riscrive_solo_la_frase_ancora_uguale(): void
    {
        $vecchia = '<p>Il diritto di recesso è escluso per i beni confezionati su misura o chiaramente personalizzati su tua richiesta, per esempio una maglia con nome e numero scelti da te (art. 59 c. 1 lett. c del Codice del consumo).</p>';
        $pagina = Page::where('slug', CondizioniDiVendita::SLUG_RECESSO)->first();
        $pagina->setTranslations('content', ['it' => $vecchia, 'en' => '<p><a href="/en/recesso">Withdraw</a></p>'])->save();

        $migrazione = require database_path('migrations/2026_09_26_150000_la_firma_della_giocatrice_e_esclusa_dal_recesso.php');
        $migrazione->up();

        $pagina->refresh();
        $this->assertStringContainsString('la firma di una giocatrice', $pagina->getTranslation('content', 'it'));
        $this->assertStringContainsString('href="/en/withdrawal"', $pagina->getTranslation('content', 'en'));

        $pagina->setTranslations('content', ['it' => '<p>Riscritta dalla redazione</p>'])->save();
        $migrazione->up();
        $this->assertSame('<p>Riscritta dalla redazione</p>', $pagina->fresh()->getTranslation('content', 'it'));
    }

    /**
     * @return array{0: Order, 1: OrderItem, 2: OrderItem}
     */
    private function ordineConUnaRigaFirmata(array $attributi = []): array
    {
        $ordine = Order::factory()->create($attributi);
        $prodotto = Product::factory()->create(['name' => ['it' => 'Maglia home', 'en' => 'Home shirt']]);

        $normale = OrderItem::factory()->create([
            'order_id' => $ordine->id,
            'product_id' => $prodotto->id,
            'quantity' => 1,
        ]);
        $firmata = OrderItem::factory()->create([
            'order_id' => $ordine->id,
            'product_id' => $prodotto->id,
            'quantity' => 1,
            'personalizzazione' => ['it' => 'Firma della giocatrice', 'en' => "Player's signature"],
            'supplemento_personalizzazione' => 15,
        ]);

        return [$ordine, $normale, $firmata];
    }

    private function dati(Order $ordine, array $righe): array
    {
        return [
            'nome' => 'Maria Bianchi',
            'email' => 'maria@example.com',
            'numero_ordine' => $ordine->order_number,
            'righe' => $righe,
            'token' => $ordine->order_token,
            'conferma' => true,
        ];
    }
}

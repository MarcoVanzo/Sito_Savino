<?php

namespace Tests\Feature\Shop;

use App\Models\Order;
use App\Models\Page;
use App\Models\RichiestaDiRecesso;
use App\Support\TestiDelleInformative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le dichiarazioni di recesso si tengono dieci anni dall'invio quando sono
 * agganciate a un ordine (sono la prova del recesso, e il rimborso si
 * prescrive in dieci anni), dodici mesi quando il numero non corrisponde a
 * nessun ordine. È quello che l'informativa dichiara. Una conservazione scritta senza un comando che
 * la applichi è una frase falsa (§21 del CLAUDE.md).
 */
class ConservazioneDeiRecessiTest extends TestCase
{
    use RefreshDatabase;

    public function test_senza_ordine_model_prune_toglie_le_dichiarazioni_oltre_i_dodici_mesi(): void
    {
        $vecchia = $this->richiesta(now()->subMonths(12)->subDay());
        $recente = $this->richiesta(now()->subMonths(11));

        $this->artisan('model:prune', ['--model' => [RichiestaDiRecesso::class]])->assertSuccessful();

        $this->assertModelMissing($vecchia);
        $this->assertModelExists($recente);
    }

    public function test_con_un_ordine_la_dichiarazione_resta_dieci_anni(): void
    {
        $ordine = Order::factory()->create();

        $diDueAnniFa = $this->richiesta(now()->subYears(2), $ordine->id);
        $quasiDieciAnni = $this->richiesta(now()->subYears(10)->addDay(), $ordine->id);
        $oltreDieciAnni = $this->richiesta(now()->subYears(10)->subDay(), $ordine->id);

        $this->artisan('model:prune', ['--model' => [RichiestaDiRecesso::class]])->assertSuccessful();

        $this->assertModelExists($diDueAnniFa);
        $this->assertModelExists($quasiDieciAnni);
        $this->assertModelMissing($oltreDieciAnni);
    }

    public function test_l_informativa_dichiara_le_due_conservazioni_in_entrambe_le_lingue(): void
    {
        $testi = TestiDelleInformative::contenuto('privacy-policy');

        $this->assertStringContainsString("Dichiarazioni di recesso inviate dal sito: 10 anni dall'invio quando si riferiscono a un ordine", $testi['it']);
        $this->assertStringContainsString('12 mesi quando il numero d', $testi['it']);
        $this->assertStringContainsString('Withdrawal notices sent from the site: 10 years from sending when they refer to an order', $testi['en']);
        $this->assertStringContainsString('12 months when the order number given does not match any order', $testi['en']);
    }

    public function test_la_revisione_del_26_settembre_riconosce_il_testo_del_25(): void
    {
        $pagina = Page::where('slug', 'privacy-policy')->first() ?? Page::factory()->create(['slug' => 'privacy-policy']);
        $pagina->setTranslations('content', [
            'it' => "<ul><li>Dichiarazioni di recesso inviate dal sito: 12 mesi dall'invio. Il rimborso che ne segue resta registrato sull'ordine.</li></ul>",
            'en' => '<p>old</p>',
        ])->save();

        TestiDelleInformative::riscriviDoveNonToccata();

        $it = $pagina->fresh()->getTranslation('content', 'it');
        $this->assertStringContainsString('10 anni dall', $it);
        $this->assertStringContainsString('EU-US Data Privacy Framework', $it);
        $this->assertStringContainsString('titolari autonomi', $it);
        $this->assertStringContainsString('pixel di tracciamento', $it);
    }

    public function test_l_informativa_distingue_responsabili_e_titolari_autonomi_senza_smentire_il_codice(): void
    {
        $testi = TestiDelleInformative::contenuto('privacy-policy');

        foreach (['it' => 'articolo 28', 'en' => 'Article 28'] as $lingua => $articolo) {
            $this->assertStringContainsString($articolo, $testi[$lingua]);
            $this->assertStringContainsString('Data Privacy Framework', $testi[$lingua]);
            foreach (['DigitalOcean', 'Resend', 'Sentry', 'ActiveCampaign', 'PayPal', 'Stripe'] as $fornitore) {
                $this->assertStringContainsString($fornitore, $testi[$lingua]);
            }
        }

        // PayPal e Stripe sono titolari autonomi, non responsabili.
        $responsabiliIt = strstr(strstr($testi['it'], 'articolo 28'), 'titolari autonomi', true);
        $this->assertStringNotContainsString('PayPal', $responsabiliIt);
        $this->assertStringNotContainsString('Stripe', $responsabiliIt);

        // Sentry non riceve l'IP (tunnel `/api/diagnostica`, send_default_pii
        // spento): l'informativa deve continuare a dirlo.
        $this->assertStringContainsString('non riceve il tuo indirizzo IP', $testi['it']);
        $this->assertFalse((bool) config('sentry.send_default_pii'));
    }

    public function test_l_informativa_dichiara_il_pixel_della_newsletter_e_la_revoca_granulare(): void
    {
        $testi = TestiDelleInformative::contenuto('privacy-policy');

        $this->assertStringContainsString('pixel di tracciamento e link tracciati', $testi['it']);
        $this->assertStringContainsString('revocare in ogni momento solo il tracciamento', $testi['it']);
        $this->assertStringContainsString('tracking pixel and tracked links', $testi['en']);
        $this->assertStringContainsString('withdraw, at any time, just the tracking', $testi['en']);
    }

    public function test_la_revisione_riscrive_solo_la_pagina_non_toccata_dalla_redazione(): void
    {
        $firma = "ordini, offerte): servono a concludere e gestire l'acquisto e a rispettare gli obblighi fiscali.";
        $pagina = Page::where('slug', 'privacy-policy')->first() ?? Page::factory()->create(['slug' => 'privacy-policy']);
        $pagina->setTranslations('content', ['it' => "<p>Testo di prima: {$firma}</p>", 'en' => '<p>old</p>'])->save();

        TestiDelleInformative::riscriviDoveNonToccata();

        $this->assertStringContainsString('10 anni dall', $pagina->fresh()->getTranslation('content', 'it'));

        $pagina->setTranslations('content', ['it' => '<p>Riscritta dalla redazione</p>'])->save();
        TestiDelleInformative::riscriviDoveNonToccata();

        $this->assertSame('<p>Riscritta dalla redazione</p>', $pagina->fresh()->getTranslation('content', 'it'));
    }

    private function richiesta($inviataIl, ?int $orderId = null): RichiestaDiRecesso
    {
        return RichiestaDiRecesso::create([
            'order_id' => $orderId,
            'numero_ordine' => 'SDB-1',
            'nome' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'lingua' => 'it',
            'inviata_il' => $inviataIl,
        ]);
    }
}

<?php

namespace Tests\Feature\Shop;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\Page;
use App\Support\CondizioniDiVendita;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Condizioni di vendita, informativa sul recesso e conferma d'ordine.
 *
 * Senza l'informativa sul recesso il termine per recedere si allunga di dodici
 * mesi (art. 53 del Codice del consumo); la conferma d'ordine deve riportare
 * su supporto durevole le informazioni precontrattuali, modulo di recesso
 * compreso (art. 51 c. 7). Fino al 25 settembre 2026 non c'era nessuna delle
 * due pagine, e l'email riportava solo articoli e totale.
 */
class CondizioniDiVenditaTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_due_pagine_nascono_pubblicate_e_non_sovrascrivono_quelle_della_redazione(): void
    {
        Page::query()->whereIn('slug', [CondizioniDiVendita::SLUG_CONDIZIONI, CondizioniDiVendita::SLUG_RECESSO])->delete();
        Page::factory()->create(['slug' => CondizioniDiVendita::SLUG_RECESSO, 'content' => ['it' => 'Testo della redazione']]);

        $create = CondizioniDiVendita::creaLePagineMancanti();

        $this->assertSame([CondizioniDiVendita::SLUG_CONDIZIONI], $create);
        $this->assertSame('Testo della redazione', Page::where('slug', CondizioniDiVendita::SLUG_RECESSO)->first()->getTranslation('content', 'it'));

        $condizioni = Page::where('slug', CondizioniDiVendita::SLUG_CONDIZIONI)->first();
        $this->assertSame('publish', $condizioni->status->value);
        $this->assertStringContainsString('Ordine con obbligo di pagamento', $condizioni->getTranslation('content', 'it'));
    }

    public function test_l_informativa_sul_recesso_contiene_il_modulo_tipo(): void
    {
        $testo = CondizioniDiVendita::contenuto(CondizioniDiVendita::SLUG_RECESSO);

        foreach (['it' => 'Modulo tipo di recesso', 'en' => 'Model withdrawal form'] as $lingua => $titolo) {
            $this->assertStringContainsString($titolo, $testo[$lingua]);
            $this->assertStringContainsString('14', $testo[$lingua]);
        }
    }

    public function test_la_conferma_d_ordine_porta_venditore_recesso_modulo_e_garanzia(): void
    {
        $ordine = Order::factory()->create(['locale' => 'it', 'condizioni_versione' => CondizioniDiVendita::VERSIONE]);

        $email = new OrderConfirmation($ordine);

        $email->assertSeeInHtml('Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a Responsabilità Limitata');
        $email->assertSeeInHtml('Diritto di recesso');
        $email->assertSeeInHtml('Modulo tipo di recesso');
        $email->assertSeeInHtml('Garanzia legale di conformità');
        $email->assertSeeInHtml($ordine->order_number);
        $this->assertSame('condizioni-di-vendita-e-recesso.pdf', $email->attachments()[0]->as);
    }

    public function test_l_allegato_pdf_si_genera(): void
    {
        $ordine = Order::factory()->create(['locale' => 'en']);

        $allegati = (new OrderConfirmation($ordine))->attachments();

        $this->assertCount(1, $allegati);
        $pdf = $allegati[0]->attachWith(
            fn ($percorso) => null,
            fn ($dati) => $dati(),
        );
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_l_istantanea_e_una_per_testo_e_cambia_con_il_testo(): void
    {
        CondizioniDiVendita::creaLePagineMancanti();

        $prima = CondizioniDiVendita::registraIstantanea('it');
        $this->assertSame($prima, CondizioniDiVendita::registraIstantanea('it'));
        $this->assertSame(64, strlen($prima));
        $this->assertSame(1, DB::table('versioni_condizioni')->where('impronta', $prima)->count());

        DB::table('pages')->where('slug', 'condizioni-di-vendita')->update([
            'content' => json_encode(['it' => '<p>Testo nuovo della redazione</p>']),
        ]);

        $dopo = CondizioniDiVendita::registraIstantanea('it');
        $this->assertNotSame($prima, $dopo);
        $this->assertSame(2, DB::table('versioni_condizioni')->count());
    }

    public function test_il_pdf_riporta_il_testo_accettato_anche_se_la_pagina_cambia_dopo(): void
    {
        // La prova del contratto: la redazione riscrive la pagina dal
        // pannello, ma l'allegato dell'ordine resta quello accettato.
        CondizioniDiVendita::creaLePagineMancanti();
        DB::table('pages')->where('slug', 'condizioni-di-vendita')->update([
            'content' => json_encode(['it' => '<p>Testo accettato al checkout</p>']),
        ]);
        $ordine = Order::factory()->create([
            'locale' => 'it',
            'condizioni_impronta' => CondizioniDiVendita::registraIstantanea('it'),
        ]);

        DB::table('pages')->where('slug', 'condizioni-di-vendita')->update([
            'content' => json_encode(['it' => '<p>Testo riscritto dopo</p>']),
        ]);

        $pagine = CondizioniDiVendita::perLAllegatoDellOrdine($ordine);

        $this->assertStringContainsString('Testo accettato al checkout', $pagine['condizioni-di-vendita']['contenuto']);
        $this->assertStringNotContainsString('Testo riscritto dopo', $pagine['condizioni-di-vendita']['contenuto']);
        $this->assertStringContainsString('Modulo tipo di recesso', $pagine['diritto-di-recesso']['contenuto']);

        // Un ordine di prima dell'impronta ripiega sul testo di oggi.
        $vecchio = Order::factory()->create(['locale' => 'it', 'condizioni_impronta' => null]);
        $this->assertStringContainsString('Testo riscritto dopo', CondizioniDiVendita::perLAllegatoDellOrdine($vecchio)['condizioni-di-vendita']['contenuto']);
    }

    public function test_l_allegato_riporta_il_testo_pubblicato_con_i_link_assoluti(): void
    {
        // Il cliente accetta la pagina che legge: se la redazione l'ha
        // ritoccata, il PDF deve essere quella, non il file dati.
        CondizioniDiVendita::creaLePagineMancanti();
        DB::table('pages')->where('slug', 'condizioni-di-vendita')->update([
            'content' => json_encode(['it' => '<p>Testo ritoccato. <a href="/recesso">Recedi</a></p>', 'en' => '']),
        ]);

        $pagine = CondizioniDiVendita::perLAllegato('it');

        $this->assertStringContainsString('Testo ritoccato', $pagine['condizioni-di-vendita']['contenuto']);
        $this->assertStringContainsString('href="'.rtrim(config('app.url'), '/').'/recesso"', $pagine['condizioni-di-vendita']['contenuto']);

        // Vuota in inglese: il testo del file dati, non una pagina bianca.
        $this->assertNotSame('', trim(strip_tags(CondizioniDiVendita::perLAllegato('en')['condizioni-di-vendita']['contenuto'])));
    }
}

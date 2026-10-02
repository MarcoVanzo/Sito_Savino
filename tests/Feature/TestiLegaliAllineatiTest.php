<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La revisione del 2 ottobre 2026 delle pagine legali dello shop: riscrive le
 * frasi rimaste come erano pubblicate, e non tocca quelle della redazione.
 */
class TestiLegaliAllineatiTest extends TestCase
{
    use RefreshDatabase;

    private function migra(): void
    {
        (require database_path('migrations/2026_10_02_160000_i_testi_legali_dicono_quello_che_il_sito_fa.php'))->up();
    }

    private function pagina(string $slug, array $contenuto, array $descrizione = ['it' => '', 'en' => '']): void
    {
        DB::table('pages')->where('slug', $slug)->delete();
        DB::table('pages')->insert([
            'slug' => $slug,
            'title' => json_encode(['it' => $slug, 'en' => $slug]),
            'template' => 'Public/ContentPage',
            'status' => 'publish',
            'content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE),
            'meta_description' => json_encode($descrizione, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function testo(string $slug, string $lingua = 'it'): string
    {
        return json_decode((string) DB::table('pages')->where('slug', $slug)->value('content'), true)[$lingua];
    }

    public function test_riscrive_le_frasi_pubblicate(): void
    {
        $this->pagina('regolamento-aste', [
            'it' => "<p>Il vincitore ha il tempo indicato nella pagina dell'asta per pagare; se non paga entro il termine, l'oggetto viene proposto al secondo miglior offerente alle condizioni della sua offerta.</p>",
            'en' => '<p>The winner has the time shown on the auction page to pay; if payment is not made in time, the item is offered to the second-highest bidder on the terms of their bid.</p>',
        ]);
        $this->pagina('informativa-fornitori', ['it' => '<p>x</p>', 'en' => '<p>x</p>'], [
            'it' => 'Informativa privacy (art. 13 GDPR) per clienti e fornitori della Savino Del Bene Volley: finalità, destinatari, conservazione e diritti.',
            'en' => 'Scritta dalla redazione',
        ]);

        $this->migra();

        $this->assertStringContainsString("all'offerente successivo in classifica", $this->testo('regolamento-aste'));
        $this->assertStringContainsString('next bidder in the ranking', $this->testo('regolamento-aste', 'en'));

        $descrizione = json_decode((string) DB::table('pages')->where('slug', 'informativa-fornitori')->value('meta_description'), true);
        $this->assertStringContainsString('fornitori e controparti contrattuali', $descrizione['it']);
        $this->assertSame('Scritta dalla redazione', $descrizione['en']);
    }

    public function test_non_tocca_il_testo_della_redazione_e_si_puo_rieseguire(): void
    {
        $this->pagina('regolamento-aste', ['it' => '<p>Regole scritte dalla redazione.</p>', 'en' => '<p>Editorial rules.</p>']);

        $this->migra();
        $this->migra();

        $this->assertSame('<p>Regole scritte dalla redazione.</p>', $this->testo('regolamento-aste'));
    }
}

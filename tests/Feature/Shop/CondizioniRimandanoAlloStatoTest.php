<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La migrazione del 26/09/2026 fa rimandare la frase sulle maglie da gara alla
 * voce «Stato dell'articolo» della scheda, solo dove e' ancora quella
 * pubblicata.
 */
class CondizioniRimandanoAlloStatoTest extends TestCase
{
    use RefreshDatabase;

    private function migrazione(): object
    {
        return require database_path('migrations/2026_09_26_160000_le_condizioni_rimandano_allo_stato_dell_articolo.php');
    }

    /**
     * @param  array<string, string>  $contenuto
     */
    private function pagina(array $contenuto): void
    {
        DB::table('pages')->where('slug', 'condizioni-di-vendita')->delete();
        DB::table('pages')->insert([
            'title' => json_encode(['it' => 'Condizioni di vendita', 'en' => 'Terms of sale']),
            'slug' => 'condizioni-di-vendita',
            'template' => 'Public/ContentPage',
            'content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE),
            'status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function contenuto(): array
    {
        return json_decode(DB::table('pages')->where('slug', 'condizioni-di-vendita')->value('content'), true);
    }

    #[Test]
    public function la_frase_pubblicata_rimanda_alla_voce_della_scheda(): void
    {
        $this->pagina([
            'it' => '<p>La garanzia non copre l\'usura normale. I prodotti autografati e le maglie da gara sono venduti nello stato descritto nella scheda.</p>',
            'en' => '<p>The guarantee does not cover normal wear. Signed products and match shirts are sold in the condition described on the product page.</p>',
        ]);

        $this->migrazione()->up();

        $contenuto = $this->contenuto();
        $this->assertStringContainsString('I prodotti autografati e le maglie da gara sono venduti nello stato descritto nella scheda alla voce «Stato dell\'articolo», riportato anche nella conferma d\'ordine.', $contenuto['it']);
        $this->assertStringContainsString('Signed products and match shirts are sold in the condition described under “Item condition” on the product page, which is also shown in the order confirmation.', $contenuto['en']);
        $this->assertStringStartsWith('<p>La garanzia non copre l\'usura normale.', $contenuto['it']);
    }

    #[Test]
    public function il_testo_riscritto_dalla_redazione_non_si_tocca(): void
    {
        $riscritto = [
            'it' => '<p>Le maglie da gara si vendono come sono: guarda le foto.</p>',
            'en' => '<p>Match shirts are sold as they are.</p>',
        ];
        $this->pagina($riscritto);

        $this->migrazione()->up();

        $this->assertSame($riscritto, $this->contenuto());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Da quando anche il browser manda errori a Sentry, l'informativa deve dirlo:
 * la migrazione riscrive la pagina solo se è ancora la versione del 25
 * settembre, e lascia stare un testo che la redazione ha già toccato.
 */
class InformativaErroriDelBrowserTest extends TestCase
{
    use RefreshDatabase;

    private function migra(): void
    {
        $migrazione = require database_path('migrations/2026_09_25_200000_l_informativa_dice_degli_errori_del_browser.php');
        $migrazione->up();
    }

    private function informativa(string $it, string $en): Page
    {
        Page::query()->where('slug', 'privacy-policy')->delete();

        return Page::factory()->create([
            'slug' => 'privacy-policy',
            'content' => ['it' => $it, 'en' => $en],
        ]);
    }

    #[Test]
    public function il_testo_del_25_settembre_si_aggiorna(): void
    {
        $pagina = $this->informativa(
            '<p>Sentry (diagnostica: senza indirizzo IP né identità di chi navigava)</p>',
            '<p>Sentry (with no IP address and no identity of whoever was browsing)</p>',
        );

        $this->migra();
        $pagina->refresh();

        $this->assertStringContainsString('passano dal nostro server prima di arrivare a Sentry', $pagina->getTranslation('content', 'it'));
        $this->assertStringContainsString('go through our server before reaching Sentry', $pagina->getTranslation('content', 'en'));
    }

    #[Test]
    public function un_testo_riscritto_dalla_redazione_non_si_tocca(): void
    {
        $pagina = $this->informativa('<p>Testo scritto dalla redazione</p>', '<p>Editorial text</p>');

        $this->migra();

        $this->assertSame('<p>Testo scritto dalla redazione</p>', $pagina->refresh()->getTranslation('content', 'it'));
    }
}

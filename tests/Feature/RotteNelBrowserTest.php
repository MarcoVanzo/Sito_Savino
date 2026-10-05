<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;
use Tighten\Ziggy\Ziggy;

/**
 * Al browser arrivano solo le rotte che il frontend chiama per nome
 * (config/ziggy.php): né il pannello né quelle che nessuna pagina usa.
 */
class RotteNelBrowserTest extends TestCase
{
    /** @return list<string> */
    private function nomiChiamatiDalFrontend(): array
    {
        $nomi = [];
        $file = Finder::create()->files()->in(resource_path('js'))->name(['*.vue', '*.js'])->notName('*.test.js');

        foreach ($file as $sorgente) {
            preg_match_all("/(?:route\\(\\s*|current\\(\\s*|routeName:\\s*)['\"]([^'\"]+)['\"]/", $sorgente->getContents(), $trovati);
            // Partita.vue sceglie fra due nomi con un ternario dentro route().
            preg_match_all("/route\\([^)]*\\?\\s*['\"]([^'\"]+)['\"]\\s*:\\s*['\"]([^'\"]+)['\"]/", $sorgente->getContents(), $ternari);
            $nomi = [...$nomi, ...$trovati[1], ...$ternari[1], ...$ternari[2]];
        }

        return array_values(array_unique($nomi));
    }

    #[Test]
    public function ogni_rotta_chiamata_dal_frontend_arriva_al_browser(): void
    {
        $nelBrowser = array_keys((new Ziggy)->toArray()['routes']);

        foreach ($this->nomiChiamatiDalFrontend() as $nome) {
            $this->assertContains($nome, $nelBrowser, "Il frontend chiama route('{$nome}'): va aggiunta in config/ziggy.php.");

            if (Route::has('en.'.$nome)) {
                $this->assertContains('en.'.$nome, $nelBrowser);
            }
        }
    }

    #[Test]
    public function al_browser_non_arriva_nessuna_rotta_che_il_frontend_non_chiama(): void
    {
        $chiamate = $this->nomiChiamatiDalFrontend();
        $inPiu = array_filter(
            array_keys((new Ziggy)->toArray()['routes']),
            fn (string $nome): bool => ! in_array(preg_replace('/^en\./', '', $nome), $chiamate, true),
        );

        $this->assertSame([], array_values($inPiu), 'Rotte in config/ziggy.php che nessuna pagina chiama.');
    }

    #[Test]
    public function il_pannello_e_i_webhook_non_arrivano_al_browser(): void
    {
        $nelBrowser = implode(' ', array_keys((new Ziggy)->toArray()['routes']));

        $this->assertStringNotContainsString('filament.', $nelBrowser);
        $this->assertStringNotContainsString('webhook', $nelBrowser);
        $this->assertLessThan(130, substr_count($nelBrowser, ' ') + 1);
    }
}

<?php

namespace Tests\Feature\Lvf;

use App\Services\Lvf\LvfClient;
use App\Services\Lvf\LvfException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La Lega serve a volte una pagina di attesa che carica quella vera via AJAX:
 * letta com'era, il sync trovava zero gare e finiva «riuscito», in silenzio.
 */
class LaPaginaDiAttesaDellaLegaSiSegueTest extends TestCase
{
    private function fixture(string $nome): string
    {
        return (string) file_get_contents(base_path("tests/Fixtures/Lvf/{$nome}"));
    }

    private function client(): LvfClient
    {
        return new LvfClient('https://www.legavolleyfemminile.it', 5, 'SavinoBot/1.0');
    }

    #[Test]
    public function dalla_pagina_di_attesa_si_legge_quella_vera(): void
    {
        Http::fake(fn (Request $richiesta) => Http::response($this->fixture(
            str_contains($richiesta->url(), 'no_cache_sa=1') ? 'risultati-tutte-le-giornate.html' : 'pagina-di-attesa.html',
        )));

        $this->assertSame($this->fixture('risultati-tutte-le-giornate.html'), $this->client()->results(2026));
        Http::assertSent(fn (Request $richiesta): bool => $richiesta->url() === 'https://www.legavolleyfemminile.it/risultati/?stagione=2026&giornata=tutte&no_cache_sa=1');
    }

    #[Test]
    public function se_anche_la_pagina_vera_e_di_attesa_il_sync_fallisce(): void
    {
        Http::fake(['*' => Http::response($this->fixture('pagina-di-attesa.html'))]);

        $this->expectException(LvfException::class);

        $this->client()->results(2026);
    }
}

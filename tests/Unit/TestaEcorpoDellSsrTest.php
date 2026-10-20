<?php

namespace Tests\Unit;

use App\Support\DatiInizialiDellaPagina;
use App\Support\TestaDellSsr;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cio' che arriva dal server SSR passa da due filtri: il `<head>` senza i
 * doppioni del layout, il corpo con il JSON della pagina neutralizzato.
 */
class TestaEcorpoDellSsrTest extends TestCase
{
    #[Test]
    public function la_testa_dell_ssr_cede_titolo_e_meta_al_layout(): void
    {
        $testa = TestaDellSsr::separa([
            '<title inertia>Shop &amp; Store</title>',
            '<meta name="description" content="Maglie &quot;ufficiali&quot;" inertia>',
            '<meta property="og:image" content="https://esempio.it/a.jpg" inertia>',
            '<meta name="robots" content="noindex, nofollow" inertia>',
        ]);

        $this->assertSame('Shop & Store', $testa['title']);
        $this->assertSame('Maglie "ufficiali"', $testa['meta']['description']);
        $this->assertSame('https://esempio.it/a.jpg', $testa['meta']['og:image']);
        $this->assertSame('<meta name="robots" content="noindex, nofollow" inertia>', $testa['resto']);
    }

    #[Test]
    public function senza_ssr_la_testa_e_vuota(): void
    {
        $this->assertSame(['title' => null, 'meta' => [], 'resto' => ''], TestaDellSsr::separa(null));
    }

    #[Test]
    public function il_corpo_dell_ssr_riceve_il_json_neutralizzato(): void
    {
        $pagina = ['component' => 'Public/NewsDetail', 'props' => ['testo' => '<!--<script>x</script>-->']];
        $corpo = '<!--[--><script data-page="app" type="application/json">{"component":"Public\/NewsDetail","props":{"testo":"<!--<script>x<\/script>-->"}}</script><div id="app"><h1>Titolo</h1></div><!--]-->';

        $riscritto = DatiInizialiDellaPagina::corpoDellSsr($corpo, $pagina);

        $this->assertStringContainsString('<script data-page="app" type="application/json">'.DatiInizialiDellaPagina::json($pagina).'</script>', $riscritto);
        $this->assertStringContainsString('<div id="app"><h1>Titolo</h1></div>', $riscritto);
        $this->assertSame(2, substr_count($riscritto, '<!--'), 'restano solo i segnaposto del frammento');
    }

    #[Test]
    public function un_corpo_senza_lo_script_della_pagina_resta_com_e(): void
    {
        $this->assertSame('<div id="app"></div>', DatiInizialiDellaPagina::corpoDellSsr('<div id="app"></div>', []));
        $this->assertSame('<script data-page="app" type="application/json">{', DatiInizialiDellaPagina::corpoDellSsr('<script data-page="app" type="application/json">{', []));
    }
}

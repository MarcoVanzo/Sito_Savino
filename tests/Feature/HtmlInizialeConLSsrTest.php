<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Con l'SSR l'HTML iniziale contiene gia' H1 e testi principali, senza
 * aspettare il JavaScript (audit SEO/ADV del 20/10/2026).
 *
 * Serve il server SSR vero (`node bootstrap/ssr/ssr.js`, dopo `npm run
 * build`): la CI lo avvia in un passo suo e lancia `--group ssr`. Senza
 * server il test si salta, come nel resto della suite.
 */
#[Group('ssr')]
class HtmlInizialeConLSsrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $vivo = Http::timeout(2)->get('http://127.0.0.1:13714/health')->json('status') === 'OK';
        } catch (\Throwable) {
            $vivo = false;
        }

        if (! $vivo) {
            $this->markTestSkipped('Server SSR non avviato (node bootstrap/ssr/ssr.js).');
        }

        config(['inertia.ssr.enabled' => true, 'inertia.ssr.url' => 'http://127.0.0.1:13714']);
        Cache::flush();
    }

    /**
     * Il corpo dentro il contenitore dell'app, cioe' cio' che ha reso il
     * server (Inertia 3 lo marca con `data-server-rendered`).
     */
    private function corpo(string $html): string
    {
        $inizio = strpos($html, '<div data-server-rendered="true" id="app">');
        $this->assertNotFalse($inizio, 'manca il contenitore dell\'app');

        $corpo = substr($html, $inizio);
        $this->assertStringNotContainsString('<div id="app"></div>', $html, 'la pagina e\' arrivata senza SSR');

        return $corpo;
    }

    #[Test]
    public function la_home_arriva_con_l_h1(): void
    {
        SiteSetting::set('hero_title', 'Savino Del Bene Volley', 'home');

        $corpo = $this->corpo($this->get('/')->assertOk()->getContent());

        $this->assertMatchesRegularExpression('#<h1[^>]*>.*Savino Del Bene Volley.*</h1>#s', $corpo);
    }

    #[Test]
    public function la_notizia_arriva_con_titolo_e_testo(): void
    {
        $notizia = Post::factory()->create([
            'title' => ['it' => 'Vittoria al tie-break a Conegliano'],
            'content' => ['it' => '<p>Una rimonta da due set sotto, chiusa 15-13 al quinto.</p>'],
        ]);

        $html = $this->get(route('news.show', $notizia->slug))->assertOk()->getContent();
        $corpo = $this->corpo($html);

        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Vittoria al tie-break a Conegliano\s*</h1>#', $corpo);
        $this->assertStringContainsString('Una rimonta da due set sotto, chiusa 15-13 al quinto.', $corpo);
        // Un titolo solo, anche con il <Head> della pagina reso dal server.
        $this->assertSame(1, substr_count($html, '<title'));
    }

    #[Test]
    public function la_scheda_prodotto_arriva_con_nome_e_prezzo(): void
    {
        $prodotto = Product::factory()->create([
            'name' => ['it' => 'Sciarpa ufficiale 2026'],
            'slug' => 'sciarpa-ufficiale-2026',
            'price' => 25,
            'sale_price' => null,
            'stock' => 4,
            'is_active' => true,
        ]);

        $corpo = $this->corpo($this->get(route('shop.product', $prodotto->slug))->assertOk()->getContent());

        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*Sciarpa ufficiale 2026\s*</h1>#', $corpo);
        $this->assertMatchesRegularExpression('#25,00#', $corpo);
    }

    #[Test]
    public function il_json_della_pagina_resta_neutralizzato(): void
    {
        // `<!--` seguito da `<script` dentro il JSON terrebbe aperto lo script
        // (DatiInizialiDellaPagina::corpoDellSsr).
        $notizia = Post::factory()->create([
            'title' => ['it' => 'Incorporamento'],
            'content' => ['it' => '<p>prima</p><!--<script>alert(1)</script>--><p>dopo</p>'],
        ]);

        $html = $this->get(route('news.show', $notizia->slug))->getContent();
        $json = substr($html, strpos($html, '<script data-page="app"'));
        $json = substr($json, 0, strpos($json, '</script>'));

        $this->assertStringNotContainsString('<!--', $json);
        $this->assertStringNotContainsString('<script>alert', $json);
    }
}

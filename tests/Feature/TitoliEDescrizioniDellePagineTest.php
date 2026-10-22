<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Http\Middleware\ServeSocialCrawlerMeta;
use App\Models\Post;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * Titoli e descrizioni dell'HTML servito: uno per pagina, mai il ripiego
 * generico fuori dalle pagine d'errore, e lo stesso titolo che poi usa il
 * client (prop `seo`). Audit SEO del 14/10/2026.
 */
class TitoliEDescrizioniDellePagineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    /**
     * @return array{title: string, description: string}
     */
    private function meta(string $indirizzo): array
    {
        $html = (string) $this->get($indirizzo)->assertOk()->getContent();

        preg_match('#<title data-inertia>(.*?)</title>#s', $html, $titolo);
        preg_match('#<meta name="description" content="([^"]*)" data-inertia="description">#', $html, $descrizione);

        return [
            'title' => html_entity_decode($titolo[1] ?? '', ENT_QUOTES),
            'description' => html_entity_decode($descrizione[1] ?? '', ENT_QUOTES),
        ];
    }

    #[Test]
    public function la_home_ha_un_titolo_solo_col_nome_della_societa_davanti(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertStringContainsString('<title data-inertia>Savino Del Bene Volley — Sito Ufficiale</title>', (string) $response->getContent());
        // Lo stesso titolo arriva al client, che non ne compone un altro.
        $this->assertSame('Savino Del Bene Volley — Sito Ufficiale', $response->viewData('page')['props']['seo']['title']);
    }

    #[Test]
    public function le_tre_squadre_del_vivaio_hanno_titoli_e_descrizioni_propri(): void
    {
        $pagine = array_map(fn (string $indirizzo) => $this->meta($indirizzo), ['/stagione/b1', '/stagione/u17', '/stagione/u15']);

        $this->assertCount(3, array_unique(array_column($pagine, 'title')));
        $this->assertCount(3, array_unique(array_column($pagine, 'description')));
        $this->assertSame('Under 17 — rosa e staff | Savino Del Bene Volley', $pagine[1]['title']);
        $this->assertStringContainsString('Under 17', $pagine[1]['description']);
        $this->assertStringContainsString('Serie C', $pagine[1]['description']);
    }

    #[Test]
    public function ogni_pagina_fissa_ha_un_titolo_suo(): void
    {
        $tabella = (new ReflectionClassConstant(ServeSocialCrawlerMeta::class, 'ROUTE_META'))->getValue();

        // Le pagine del checkout delle aste riusano di proposito i testi di
        // quelle dello shop: sono le stesse pagine private, non indicizzate.
        $chiavi = array_unique(array_values($tabella));
        $titoli = array_map(fn (string $chiave) => __('site.social.'.$chiave.'.title', [], 'it'), $chiavi);

        $this->assertSame(count($titoli), count(array_unique($titoli)), 'Due chiavi di ROUTE_META con lo stesso titolo');
    }

    #[Test]
    public function la_categoria_dello_shop_non_usa_la_descrizione_dello_shop(): void
    {
        ProductCategory::factory()->create(['name' => 'Abbigliamento di prova', 'slug' => 'abbigliamento-di-prova', 'description' => null]);

        $meta = $this->meta('/shop/categoria/abbigliamento-di-prova');

        $this->assertStringContainsString('Abbigliamento di prova', $meta['description']);
        $this->assertNotSame(__('site.social.shop.description'), $meta['description']);
    }

    #[Test]
    public function una_notizia_con_il_titolo_lungo_perde_la_coda_col_nome_della_societa(): void
    {
        $lunga = Post::factory()->create([
            'title' => 'La Savino Del Bene vince a Novara al tie-break e resta in testa',
            'slug' => 'vittoria-lunga',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);
        $breve = Post::factory()->create([
            'title' => 'Vittoria a Novara',
            'slug' => 'vittoria-breve',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $this->assertSame('La Savino Del Bene vince a Novara al tie-break e resta in testa', $this->meta('/news/'.$lunga->slug)['title']);
        $this->assertSame('Vittoria a Novara | Savino Del Bene Volley', $this->meta('/news/'.$breve->slug)['title']);
    }

    #[Test]
    public function il_ripiego_generico_resta_alle_pagine_d_errore(): void
    {
        $generica = __('site.default_description');

        foreach (['/', '/stagione', '/stagione/u15', '/news', '/shop', '/gallery', '/contatti'] as $indirizzo) {
            $this->assertNotSame($generica, $this->meta($indirizzo)['description'], $indirizzo);
        }
    }
}

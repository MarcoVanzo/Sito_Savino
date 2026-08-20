<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le anteprime social sono servite da ServeSocialCrawlerMeta, che sostituisce
 * l'SSR. Copriva solo home, news e poche pagine fisse: tutto il resto — i
 * prodotti dello shop, le pagine inglesi, le pagine del CMS — finiva sul
 * fallback generico, con il logo al posto della foto.
 */
class SocialCrawlerMetaTest extends TestCase
{
    use RefreshDatabase;

    private const CRAWLER = ['User-Agent' => 'WhatsApp/2.23'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    #[Test]
    public function il_prodotto_dello_shop_ha_titolo_e_immagine_propri(): void
    {
        $product = Product::factory()->create([
            'name' => 'Maglia gara 2026',
            'slug' => 'maglia-gara-2026',
            'short_description' => 'La maglia ufficiale della stagione.',
            'is_active' => true,
        ]);

        $this->withHeaders(self::CRAWLER)
            ->get("/shop/prodotto/{$product->slug}")
            ->assertStatus(200)
            ->assertSee('Maglia gara 2026', false)
            ->assertSee('La maglia ufficiale della stagione.', false)
            ->assertSee('og:type" content="product', false);
    }

    #[Test]
    public function il_prodotto_inesistente_resta_un_404(): void
    {
        // Il route model binding risolve prima del middleware: giusto così,
        // un'anteprima per una pagina che non esiste sarebbe fuorviante.
        $this->withHeaders(self::CRAWLER)
            ->get('/shop/prodotto/non-esiste')
            ->assertStatus(404);
    }

    #[Test]
    public function le_pagine_inglesi_dichiarano_la_lingua_giusta(): void
    {
        $response = $this->withHeaders(self::CRAWLER)->get('/en/news');

        $response->assertStatus(200)
            ->assertSee('<html lang="en">', false)
            ->assertSee('og:locale" content="en_GB', false);

        // Il prefisso di lingua non deve più far cadere la pagina sul
        // fallback generico della home.
        $response->assertSee('News —', false);
    }

    #[Test]
    public function le_pagine_del_cms_usano_il_proprio_titolo(): void
    {
        Page::factory()->create([
            'slug' => 'storia',
            'title' => 'La nostra storia',
            'meta_description' => 'Dal 1995 a oggi.',
            'status' => PostStatus::Published,
        ]);

        $this->withHeaders(self::CRAWLER)
            ->get('/societa/storia')
            ->assertStatus(200)
            ->assertSee('La nostra storia', false)
            ->assertSee('Dal 1995 a oggi.', false);
    }

    #[Test]
    public function il_prodotto_senza_short_description_usa_la_descrizione_lunga(): void
    {
        $product = Product::factory()->create([
            'name' => 'Sciarpa ufficiale',
            'slug' => 'sciarpa-ufficiale',
            'short_description' => null,
            'description' => '<p>Una sciarpa in maglia con i colori sociali, prodotta in Toscana.</p>',
            'is_active' => true,
        ]);

        $this->withHeaders(self::CRAWLER)
            ->get("/shop/prodotto/{$product->slug}")
            ->assertStatus(200)
            ->assertSee('Una sciarpa in maglia con i colori sociali', false);
    }

    #[Test]
    public function la_pagina_cms_senza_meta_description_ricade_sul_contenuto(): void
    {
        Page::factory()->create([
            'slug' => 'hospitality',
            'title' => 'Hospitality',
            'meta_description' => null,
            'excerpt' => null,
            'content' => '<p>Pacchetti hospitality per le aziende al Pala BigMat.</p>',
            'status' => PostStatus::Published,
        ]);

        $this->withHeaders(self::CRAWLER)
            ->get('/sponsor/hospitality')
            ->assertStatus(200)
            ->assertSee('Hospitality —', false)
            ->assertSee('Pacchetti hospitality per le aziende', false);
    }

    #[Test]
    public function la_bozza_non_e_esposta_al_crawler(): void
    {
        $post = Post::factory()->create([
            'slug' => 'annuncio-riservato',
            'title' => 'Annuncio riservato',
            'status' => PostStatus::Draft,
        ]);

        $this->withHeaders(self::CRAWLER)
            ->get("/news/{$post->slug}")
            ->assertStatus(200)
            ->assertDontSee('Annuncio riservato', false);
    }
}

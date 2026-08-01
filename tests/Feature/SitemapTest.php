<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La sitemap era un file committato in `public/`, generato una volta sulla
 * macchina di sviluppo: in produzione serviva 378 URL `http://localhost:8000`.
 * Questi test bloccano il ritorno di entrambi i difetti, il file statico e
 * l'host sbagliato.
 */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function la_sitemap_e_servita_come_xml(): void
    {
        $this->get('/sitemap.xml')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<urlset', false);
    }

    #[Test]
    public function gli_url_seguono_app_url(): void
    {
        // Si testa il builder direttamente: è la generazione degli URL che deve
        // seguire APP_URL, la rotta è già coperta dagli altri test. Passare
        // dallo stack HTTP con un root URL forzato renderebbe il test
        // dipendente dall'host della richiesta.
        config(['app.url' => 'https://www.esempio-savino.it']);
        url()->forceRootUrl('https://www.esempio-savino.it');
        url()->forceScheme('https');

        $xml = app(SitemapBuilder::class)->build()->render();

        $this->assertStringContainsString('https://www.esempio-savino.it/stagione', $xml);
        $this->assertStringNotContainsString('localhost', $xml);
    }

    #[Test]
    public function le_news_pubblicate_sono_in_sitemap_le_bozze_no(): void
    {
        $pubblicata = Post::factory()->create([
            'slug' => 'notizia-pubblicata',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        Post::factory()->create([
            'slug' => 'notizia-in-bozza',
            'status' => PostStatus::Draft,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee("/news/{$pubblicata->slug}", false);
        $response->assertDontSee('/news/notizia-in-bozza', false);
    }

    #[Test]
    public function non_esiste_un_file_statico_che_scavalca_la_rotta(): void
    {
        // Un `public/sitemap.xml` verrebbe servito dal web server prima che
        // Laravel veda la richiesta, riportando in produzione il file sbagliato.
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
    }
}

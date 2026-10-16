<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Player;
use App\Models\Post;
use App\Models\Product;
use App\Models\Reindirizzamento;
use App\Models\Roster;
use App\Models\Season;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Services\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Audit SEO del 14/10/2026: immagini delle notizie in sitemap, pagine delle
 * atlete, sitemap delle notizie recenti con indice, e nessun indirizzo che
 * il pannello reindirizza.
 */
class SitemapConImmaginiNotizieEAtleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    #[Test]
    public function la_notizia_porta_la_sua_copertina(): void
    {
        $post = Post::factory()->create(['slug' => 'con-copertina', 'status' => PostStatus::Published, 'published_at' => now()->subDay()]);
        $post->addMedia(UploadedFile::fake()->image('copertina.jpg', 1600, 900))->toMediaCollection('cover', 'public');

        $xml = (string) $this->get('/sitemap-notizie.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/news/con-copertina', $xml);
        $this->assertMatchesRegularExpression('#<image:image>\s*<image:loc>[^<]*copertina\.jpg[^<]*</image:loc>#', $xml);
    }

    #[Test]
    public function le_atlete_della_prima_squadra_sono_in_sitemap(): void
    {
        $stagione = Season::factory()->create(['is_current' => true]);
        $squadra = Team::factory()->create(['slug' => 'savino-del-bene-volley', 'category' => 'A1', 'is_internal' => true]);
        $atleta = Player::factory()->create(['first_name' => 'Ekaterina', 'last_name' => 'Antropova']);
        Roster::factory()->create(['player_id' => $atleta->id, 'team_id' => $squadra->id, 'season_id' => $stagione->id]);

        $this->get('/sitemap-atlete.xml')->assertSee('/stagione/atleta/'.$atleta->id.'-ekaterina-antropova', false);
    }

    #[Test]
    public function un_indirizzo_reindirizzato_dal_pannello_resta_fuori(): void
    {
        Post::factory()->create(['slug' => 'spostata', 'status' => PostStatus::Published, 'published_at' => now()->subDay()]);
        Reindirizzamento::create(['from_path' => '/news/spostata', 'to_path' => '/news', 'status_code' => '301']);

        $this->get('/sitemap-notizie.xml')->assertDontSee('/news/spostata', false);
    }

    #[Test]
    public function la_sitemap_delle_notizie_ha_solo_gli_ultimi_due_giorni(): void
    {
        Post::factory()->create(['slug' => 'di-ieri', 'title' => 'Notizia di ieri', 'status' => PostStatus::Published, 'published_at' => now()->subDay()]);
        Post::factory()->create(['slug' => 'di-un-mese-fa', 'status' => PostStatus::Published, 'published_at' => now()->subMonth()]);

        $this->get('/sitemap-news.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('/news/di-ieri', false)
            ->assertSee('<news:title>Notizia di ieri</news:title>', false)
            ->assertDontSee('/news/di-un-mese-fa', false);
    }

    #[Test]
    public function l_indice_elenca_le_sitemap_delle_sezioni_e_robots_lo_dichiara(): void
    {
        foreach (['/sitemap-index.xml', '/sitemap.xml'] as $indirizzo) {
            $xml = (string) $this->get($indirizzo)
                ->assertOk()
                ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
                ->getContent();

            $this->assertStringContainsString('<sitemapindex', $xml);
            preg_match_all('#<loc>([^<]+)</loc>#', $xml, $sitemap);
            $this->assertSame([
                url('/sitemap-pagine.xml'),
                url('/sitemap-notizie.xml'),
                url('/sitemap-prodotti.xml'),
                url('/sitemap-atlete.xml'),
                url('/sitemap-gallery.xml'),
                url('/sitemap-news.xml'),
            ], $sitemap[1]);
        }

        $robots = (string) file_get_contents(public_path('robots.txt'));
        preg_match_all('#^Sitemap: (.+)$#m', $robots, $dichiarate);
        $this->assertSame([
            'https://savinodelbenevolley.it/sitemap.xml',
            'https://savinodelbenevolley.it/sitemap-news.xml',
        ], $dichiarate[1]);
    }

    #[Test]
    public function i_prodotti_disponibili_sono_in_sitemap_con_le_foto(): void
    {
        $disponibile = Product::factory()->create(['slug' => 'maglia-gara', 'name' => ['it' => 'Maglia gara'], 'is_active' => true, 'stock' => 5]);
        $disponibile->addMedia(UploadedFile::fake()->image('maglia.jpg', 1200, 1200))->toMediaCollection('products', 'public');
        Product::factory()->create(['slug' => 'sciarpa-esaurita', 'is_active' => true, 'stock' => 0]);
        Product::factory()->create(['slug' => 'felpa-spenta', 'is_active' => false, 'stock' => 5]);

        $xml = (string) $this->get('/sitemap-prodotti.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('/shop/prodotto/maglia-gara'), $xml);
        $this->assertMatchesRegularExpression('#<image:loc>[^<]*maglia\.jpg[^<]*</image:loc>#', $xml);
        $this->assertStringNotContainsString('sciarpa-esaurita', $xml);
        $this->assertStringNotContainsString('felpa-spenta', $xml);
    }

    #[Test]
    public function a_negozio_chiuso_i_prodotti_non_sono_in_sitemap(): void
    {
        SiteSetting::set('shop.enabled', false);
        Product::factory()->create(['slug' => 'maglia-gara', 'is_active' => true, 'stock' => 5]);

        $xml = (string) $this->get('/sitemap-prodotti.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('maglia-gara', $xml);
    }

    #[Test]
    public function l_indice_data_le_parti_con_l_ultima_modifica_e_non_con_l_ora_di_generazione(): void
    {
        $post = Post::factory()->create(['slug' => 'ultima-modifica', 'status' => PostStatus::Published, 'published_at' => now()->subDays(10)]);
        Post::query()->whereKey($post->id)->update(['updated_at' => now()->subDays(5)->startOfMinute()]);
        $modificata = now()->subDays(5)->startOfMinute()->format(DATE_ATOM);

        // Rigenerata tre ore dopo senza novità: la data non cambia.
        $this->get('/sitemap-notizie.xml')->assertOk();
        $this->travel(3)->hours();
        app(SitemapBuilder::class)->refresh();

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/sitemap-notizie.xml').'</loc>'."\n".'    <lastmod>'.$modificata.'</lastmod>', $xml);
    }

    #[Test]
    public function chiuso_lo_shop_i_prodotti_spariscono_subito_dalla_sitemap(): void
    {
        Product::factory()->create(['slug' => 'maglia-gara', 'is_active' => true, 'stock' => 5]);
        $this->assertStringContainsString('maglia-gara', (string) $this->get('/sitemap-prodotti.xml')->getContent());

        SiteSetting::set('shop.enabled', '0');

        $this->assertStringNotContainsString('maglia-gara', (string) $this->get('/sitemap-prodotti.xml')->getContent());
    }

    #[Test]
    public function la_sitemap_delle_notizie_recenti_ha_il_tag_news(): void
    {
        Post::factory()->create(['slug' => 'appena-uscita', 'title' => ['it' => 'Vittoria a Novara'], 'status' => PostStatus::Published, 'published_at' => now()->subHours(3)]);

        $xml = (string) $this->get('/sitemap-news.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"', $xml);
        $this->assertMatchesRegularExpression('#<news:publication>\s*<news:name>Savino Del Bene Volley</news:name>\s*<news:language>it</news:language>\s*</news:publication>#', $xml);
        $this->assertStringContainsString('<news:title>Vittoria a Novara</news:title>', $xml);
        $this->assertMatchesRegularExpression('#<news:publication_date>[^<]+[+-]\d{2}:\d{2}</news:publication_date>#', $xml);
    }
}

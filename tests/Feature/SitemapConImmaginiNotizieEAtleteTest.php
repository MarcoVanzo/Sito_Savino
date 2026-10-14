<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Player;
use App\Models\Post;
use App\Models\Reindirizzamento;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
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

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

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

        $this->get('/sitemap.xml')->assertSee('/stagione/atleta/'.$atleta->id.'-ekaterina-antropova', false);
    }

    #[Test]
    public function un_indirizzo_reindirizzato_dal_pannello_resta_fuori(): void
    {
        Post::factory()->create(['slug' => 'spostata', 'status' => PostStatus::Published, 'published_at' => now()->subDay()]);
        Reindirizzamento::create(['from_path' => '/news/spostata', 'to_path' => '/news', 'status_code' => '301']);

        $this->get('/sitemap.xml')->assertDontSee('/news/spostata', false);
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
    public function l_indice_elenca_le_due_sitemap_e_robots_lo_dichiara(): void
    {
        $this->get('/sitemap-index.xml')
            ->assertOk()
            ->assertSee('<sitemapindex', false)
            ->assertSee(url('/sitemap.xml'), false)
            ->assertSee(url('/sitemap-news.xml'), false);

        $robots = (string) file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Sitemap: https://savinodelbenevolley.it/sitemap-index.xml', $robots);
        $this->assertStringContainsString('Sitemap: https://savinodelbenevolley.it/sitemap-news.xml', $robots);
    }
}

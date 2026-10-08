<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GalleryImage;
use App\Models\Game;
use App\Models\HeroSlide;
use App\Models\Player;
use App\Models\Post;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use App\Services\GalleryArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function cache_is_cleared_when_roster_is_created(): void
    {
        // La create stessa triggerà saved() — verifichiamo che la cache si svuoti
        Cache::put('public:stagione', 'cached_data', now()->addMinutes(30));

        Roster::factory()->create();

        $this->assertNull(Cache::get('public:stagione'));
    }

    /**
     * Le varianti della gallery per atleta si buttavano una query per atleta e
     * per lingua: adesso basta una generazione nuova nel nome della chiave.
     */
    #[Test]
    public function una_foto_salvata_butta_le_varianti_della_gallery_per_atleta(): void
    {
        $atleta = Player::factory()->create();
        $archivio = app(GalleryArchive::class);
        $prima = $archivio->chiave($atleta, 'it');
        Cache::put($prima, ['vecchia'], now()->addHour());

        GalleryImage::factory()->create();

        $this->assertNotSame($prima, $archivio->chiave($atleta, 'it'));
        $this->assertSame($archivio->chiave($atleta, 'it'), $archivio->chiave($atleta, 'it'));
    }

    #[Test]
    public function cache_is_cleared_when_player_is_updated(): void
    {
        // Creare il player PRIMA di popolare la cache,
        // altrimenti la create() svuota la cache e il test è un falso positivo
        $player = Player::factory()->create();

        Cache::put('public:roster_page', 'cached_data', now()->addMinutes(30));
        Cache::put('public:stagione', 'cached_data', now()->addMinutes(30));

        $player->update(['first_name' => 'Nuova']);

        $this->assertNull(Cache::get('public:roster_page'));
        $this->assertNull(Cache::get('public:stagione'));
    }

    /**
     * I controller pubblici suffissano sempre la locale nelle chiavi di cache
     * (es. "public:stagione:it"): l'observer deve invalidare anche quelle varianti.
     */
    #[Test]
    public function cache_is_cleared_for_locale_suffixed_keys(): void
    {
        $roster = Roster::factory()->create();

        Cache::put('public:stagione:it', 'cached_data', now()->addMinutes(30));
        Cache::put('public:stagione:en', 'cached_data', now()->addMinutes(30));
        Cache::put('public:roster_page:it', 'cached_data', now()->addMinutes(30));

        $roster->update(['jersey_number' => 99]);

        $this->assertNull(Cache::get('public:stagione:it'));
        $this->assertNull(Cache::get('public:stagione:en'));
        $this->assertNull(Cache::get('public:roster_page:it'));
    }

    #[Test]
    public function post_cache_is_cleared_for_locale_suffixed_keys(): void
    {
        $categoria = Category::factory()->create(['slug' => 'comunicati']);
        $post = Post::factory()->create(['slug' => 'una-notizia']);
        $post->categories()->attach($categoria);

        Cache::put('public:home:it', 'cached_data', now()->addMinutes(30));
        Cache::put('public:news:it:cat:all:page:1', 'cached_data', now()->addMinutes(30));
        Cache::put('public:news:it:cat:comunicati:page:1', 'cached_data', now()->addMinutes(30));
        Cache::put('public:news:it:una-notizia', 'cached_data', now()->addMinutes(30));
        Cache::put('public:news_categories:it', 'cached_data', now()->addMinutes(30));

        $post->update(['title' => 'Nuovo titolo']);

        $this->assertNull(Cache::get('public:home:it'));
        $this->assertNull(Cache::get('public:news:it:cat:all:page:1'));
        // Anche le liste filtrate per categoria: una notizia modificata può
        // comparire o sparire da una di quelle.
        $this->assertNull(Cache::get('public:news:it:cat:comunicati:page:1'));
        $this->assertNull(Cache::get('public:news:it:una-notizia'));
        $this->assertNull(Cache::get('public:news_categories:it'));
    }

    #[Test]
    public function game_cache_is_cleared_for_each_competition(): void
    {
        $game = Game::factory()->create();

        Cache::put('public:risultati:Campionato:it', 'cached_data', now()->addMinutes(30));
        Cache::put('public:risultati:Champions League:en', 'cached_data', now()->addMinutes(30));

        $game->update(['location' => 'PalaEstra']);

        $this->assertNull(Cache::get('public:risultati:Campionato:it'));
        $this->assertNull(Cache::get('public:risultati:Champions League:en'));
    }

    #[Test]
    public function cache_is_cleared_when_season_is_deleted(): void
    {
        // Creare la season PRIMA di popolare la cache
        $season = Season::factory()->create();

        Cache::put('public:risultati', 'cached_data', now()->addMinutes(30));

        $season->delete();

        $this->assertNull(Cache::get('public:risultati'));
    }

    /**
     * Gli slide sono il primo schermo della homepage e li si cambia spesso:
     * senza l'observer restavano quelli vecchi per i cinque minuti di
     * `public:home`, e in redazione sembrava che il salvataggio non avesse
     * funzionato.
     */
    #[Test]
    public function cache_della_home_svuotata_quando_cambia_uno_slide(): void
    {
        $slide = HeroSlide::factory()->create();

        Cache::put('public:home', 'cached_data', now()->addMinutes(30));
        Cache::put('public:home:it', 'cached_data', now()->addMinutes(30));
        Cache::put('public:home:en', 'cached_data', now()->addMinutes(30));

        $slide->update(['title' => 'Nuovo titolo']);

        $this->assertNull(Cache::get('public:home'));
        $this->assertNull(Cache::get('public:home:it'));
        $this->assertNull(Cache::get('public:home:en'));
    }

    #[Test]
    public function cache_della_home_svuotata_quando_uno_slide_e_cancellato(): void
    {
        $slide = HeroSlide::factory()->create();

        Cache::put('public:home:it', 'cached_data', now()->addMinutes(30));

        $slide->delete();

        $this->assertNull(Cache::get('public:home:it'));
    }

    #[Test]
    public function cache_is_cleared_when_team_is_updated(): void
    {
        // Creare il team PRIMA di popolare la cache
        $team = Team::factory()->create();

        Cache::put('public:stagione:b1', 'cached_data', now()->addMinutes(30));
        Cache::put('public:risultati', 'cached_data', now()->addMinutes(30));

        $team->update(['name' => 'Nuovo Nome']);

        $this->assertNull(Cache::get('public:stagione:b1'));
        $this->assertNull(Cache::get('public:risultati'));
    }
}

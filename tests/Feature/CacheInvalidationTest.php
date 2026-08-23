<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Game;
use App\Models\HeroSlide;
use App\Models\Player;
use App\Models\Post;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_cache_is_cleared_when_roster_is_created(): void
    {
        // La create stessa triggerà saved() — verifichiamo che la cache si svuoti
        Cache::put('public:stagione', 'cached_data', now()->addMinutes(30));

        Roster::factory()->create();

        $this->assertNull(Cache::get('public:stagione'));
    }

    public function test_cache_is_cleared_when_player_is_updated(): void
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
    public function test_cache_is_cleared_for_locale_suffixed_keys(): void
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

    public function test_post_cache_is_cleared_for_locale_suffixed_keys(): void
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

    public function test_game_cache_is_cleared_for_each_competition(): void
    {
        $game = Game::factory()->create();

        Cache::put('public:risultati:Campionato:it', 'cached_data', now()->addMinutes(30));
        Cache::put('public:risultati:Champions League:en', 'cached_data', now()->addMinutes(30));

        $game->update(['location' => 'PalaEstra']);

        $this->assertNull(Cache::get('public:risultati:Campionato:it'));
        $this->assertNull(Cache::get('public:risultati:Champions League:en'));
    }

    public function test_cache_is_cleared_when_season_is_deleted(): void
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
    public function test_cache_della_home_svuotata_quando_cambia_uno_slide(): void
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

    public function test_cache_della_home_svuotata_quando_uno_slide_e_cancellato(): void
    {
        $slide = HeroSlide::factory()->create();

        Cache::put('public:home:it', 'cached_data', now()->addMinutes(30));

        $slide->delete();

        $this->assertNull(Cache::get('public:home:it'));
    }

    public function test_cache_is_cleared_when_team_is_updated(): void
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

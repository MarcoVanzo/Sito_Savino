<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Player;
use App\Models\Post;
use App\Models\Product;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
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

        // 404 come per chiunque altro: NewsController fa firstOrFail sullo
        // scope published. Prima il middleware si fermava qui e restituiva 200
        // con l'anteprima generica delle news, cioè una pagina che non esiste.
        $this->withHeaders(self::CRAWLER)
            ->get("/news/{$post->slug}")
            ->assertStatus(404)
            ->assertDontSee('Annuncio riservato', false);
    }

    #[Test]
    public function la_pagina_inglese_con_slug_tradotto_non_cade_sul_ripiego(): void
    {
        // `/contatti` in inglese è `/en/contacts`: con la tabella indicizzata
        // per percorso il middleware non la riconosceva e annunciava la home.
        $this->withHeaders(self::CRAWLER)
            ->get('/en/contacts')
            ->assertStatus(200)
            ->assertSee('Contacts', false)
            ->assertDontSee('Sito Ufficiale', false);
    }

    #[Test]
    public function la_pagina_senza_pagina_cms_e_tradotta(): void
    {
        // `/stagione` non ha una pagina del CMS: i testi vengono dalle
        // traduzioni, e in inglese devono essere quelli inglesi.
        $this->withHeaders(self::CRAWLER)
            ->get('/stagione')
            ->assertStatus(200)
            ->assertSee('Stagione — Savino Del Bene Volley', false);

        $this->withHeaders(self::CRAWLER)
            ->get('/en/stagione')
            ->assertStatus(200)
            ->assertSee('Season — Savino Del Bene Volley', false)
            ->assertSee('lang="en"', false);
    }

    #[Test]
    public function la_scheda_atleta_ha_nome_e_foto(): void
    {
        $roster = $this->atletaInRosa();
        $player = $roster->player;
        $slug = $player->id.'-'.Str::slug($player->full_name);

        $this->withHeaders(self::CRAWLER)
            ->get("/stagione/atleta/{$slug}")
            ->assertStatus(200)
            ->assertSee($player->full_name, false)
            ->assertSee('og:type" content="profile', false)
            // La foto dell'atleta, non il logo: è tutta la differenza fra
            // un'anteprima che invoglia ad aprire il link e una che no.
            ->assertSee('foto-atleta', false)
            ->assertDontSee('og:image" content="'.url('/images/logo.png'), false);
    }

    #[Test]
    public function l_atleta_fuori_rosa_resta_un_404(): void
    {
        $fuoriRosa = Player::factory()->create(['first_name' => 'Nome', 'last_name' => 'Inventato']);

        $this->withHeaders(self::CRAWLER)
            ->get("/stagione/atleta/{$fuoriRosa->id}-nome-inventato")
            ->assertStatus(404);
    }

    #[Test]
    public function la_rotta_di_rimando_resta_una_redirezione(): void
    {
        // `/ticketing` rimanda a `/ticketing/biglietteria`: servire 200 con
        // un'anteprima generica spezzava la catena e dava al crawler il titolo
        // sbagliato.
        $this->withHeaders(self::CRAWLER)
            ->get('/ticketing')
            ->assertRedirect();
    }

    #[Test]
    public function un_indirizzo_inesistente_resta_un_404(): void
    {
        $this->withHeaders(self::CRAWLER)
            ->get('/pagina-che-non-esiste')
            ->assertStatus(404);
    }

    /**
     * Atleta della prima squadra nella stagione corrente, come la cerca
     * PublicController per `/stagione/atleta/{slug}`.
     */
    private function atletaInRosa(): Roster
    {
        $stagione = Season::factory()->current()->create();
        $squadra = Team::factory()->create([
            'slug' => 'savino-del-bene-volley',
            'category' => 'A1',
            'is_internal' => true,
        ]);

        $player = Player::factory()->create([
            'first_name' => 'Ekaterina',
            'last_name' => 'Antropova',
        ]);

        // Senza foto ufficiale di stagione l'accessor di Roster ripiega su
        // quella della scheda atleta: è il caso normale a inizio stagione.
        $player->addMedia(UploadedFile::fake()->image('foto-atleta.jpg', 600, 600))
            ->toMediaCollection('players', 'public');

        return Roster::factory()->create([
            'player_id' => $player->id,
            'team_id' => $squadra->id,
            'season_id' => $stagione->id,
        ]);
    }
}

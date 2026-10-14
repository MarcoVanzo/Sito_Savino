<?php

namespace Tests\Feature;

use App\Enums\PlayerPosition;
use App\Enums\SponsorTier;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Player;
use App\Models\Post;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Sponsor;
use App\Models\Team;
use App\Services\GalleryArchive;
use App\Support\TestiSeoDellaFoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I testi alternativi che il backend manda con le immagini (audit SEO del
 * 14/10/2026: in home 92 immagini su 179 senza alt).
 *
 * Copertina della notizia, logo dello sponsor e foto dell'atleta hanno un
 * campo scritto dalla redazione e un ripiego; le foto della gallery dicono
 * album e data, mai i nomi delle persone (riconoscimento dei volti, minori).
 */
class TestiAlternativiDelleImmaginiPubblicheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    #[Test]
    public function la_copertina_della_notizia_usa_il_testo_della_redazione_altrimenti_il_titolo(): void
    {
        $conTesto = Post::factory()->create(['title' => 'Vittoria a Conegliano', 'testo_alternativo' => 'Le atlete esultano a fine gara', 'published_at' => now()->subDay()]);
        $senza = Post::factory()->create(['title' => 'Presentazione della squadra', 'published_at' => now()->subDays(2)]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('latestNews.0.image_alt', 'Le atlete esultano a fine gara')
                ->where('latestNews.1.image_alt', 'Presentazione della squadra'));

        $this->get('/news')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('posts.data.0.image_alt', 'Le atlete esultano a fine gara')
                ->where('posts.data.1.image_alt', 'Presentazione della squadra'));

        $this->get('/news/'.$senza->slug)
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('post.image_alt', 'Presentazione della squadra'));

        $this->assertSame('Le atlete esultano a fine gara', $conTesto->testoAlternativoDellaCopertina('it'));
    }

    #[Test]
    public function il_logo_dello_sponsor_usa_il_testo_della_redazione_altrimenti_il_nome(): void
    {
        Sponsor::factory()->create(['name' => 'Estra', 'tier' => SponsorTier::Title, 'testo_alternativo' => 'Estra, energia per lo sport']);
        Sponsor::factory()->create(['name' => 'Conad', 'tier' => SponsorTier::Main]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('sponsor.0.logo_alt', 'Estra, energia per lo sport')
                ->where('sponsor.1.logo_alt', 'Conad'));
    }

    #[Test]
    public function la_foto_della_prima_squadra_dice_il_nome_quella_del_vivaio_no(): void
    {
        $stagione = Season::factory()->create(['is_current' => true]);
        $primaSquadra = Team::factory()->create(['slug' => 'savino-del-bene-volley', 'category' => 'A1', 'is_internal' => true]);
        $this->tessera(Player::factory()->create(['first_name' => 'Maja', 'last_name' => 'Ognjenovic']), $primaSquadra, $stagione);
        $vivaio = Team::where('category', 'U17')->where('is_internal', true)->firstOrFail();
        $this->tessera(Player::factory()->create(['first_name' => 'Giulia', 'last_name' => 'Bianchi']), $vivaio, $stagione);

        $this->get('/stagione')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('roster.0.photo_alt', 'Maja Ognjenovic, Savino Del Bene Volley'));

        $this->get('/stagione/u17')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('roster.0.photo_alt', 'Atleta del settore giovanile, Savino Del Bene Volley'));
    }

    #[Test]
    public function il_testo_scritto_per_la_foto_dell_atleta_vince_sul_ripiego(): void
    {
        $atleta = Player::factory()->create(['first_name' => 'Maja', 'last_name' => 'Ognjenovic', 'testo_alternativo' => 'Maja Ognjenovic in palleggio']);

        $this->assertSame('Maja Ognjenovic in palleggio', $atleta->testoAlternativoDellaFoto());
        $this->assertSame('Maja Ognjenovic in palleggio', $atleta->testoAlternativoDellaFoto(conIlNome: false));
    }

    #[Test]
    public function la_foto_della_gallery_dice_album_e_data_senza_i_nomi_delle_persone(): void
    {
        $album = GalleryEvent::factory()->create(['title' => 'Savino vs Conegliano', 'event_date' => '2026-09-20']);
        $foto = GalleryImage::factory()->create(['gallery_event_id' => $album->id, 'is_active' => true]);
        $atleta = Player::factory()->create(['first_name' => 'Lucia', 'last_name' => 'Bosetti']);
        DB::table('gallery_image_person')->insert([
            'gallery_image_id' => $foto->id,
            'person_type' => Player::class,
            'person_id' => $atleta->id,
            'confidence_score' => 99.2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foto = $foto->fresh();
        TestiSeoDellaFoto::applica($foto);
        $foto->saveQuietly();

        $testi = TestiSeoDellaFoto::componi($foto->fresh());
        $this->assertSame('Savino Del Bene Volley - Savino vs Conegliano - 20/09/2026', $testi['alt']);
        $this->assertStringContainsString('Lucia Bosetti', $testi['titolo'], 'il titolo resta la didascalia con i nomi');

        $voce = app(GalleryArchive::class)->costruisci(null, 'it')[0];
        $this->assertSame('Savino Del Bene Volley - Savino vs Conegliano - 20/09/2026', $voce['alt']);
        $this->assertStringNotContainsString('Bosetti', $voce['alt']);
        $this->assertStringContainsString('Lucia Bosetti', $voce['title']);
    }

    #[Test]
    public function una_foto_senza_album_resta_con_il_nome_della_squadra(): void
    {
        $this->assertSame('Savino Del Bene Volley', TestiSeoDellaFoto::altDellAlbum(null, null));
        $this->assertSame('Savino Del Bene Volley - Allenamento', TestiSeoDellaFoto::altDellAlbum(' Allenamento ', ''));
    }

    private function tessera(Player $atleta, Team $squadra, Season $stagione): void
    {
        Roster::factory()->create([
            'player_id' => $atleta->id,
            'team_id' => $squadra->id,
            'season_id' => $stagione->id,
            'jersey_number' => 7,
            'role' => PlayerPosition::Setter,
        ]);
    }
}

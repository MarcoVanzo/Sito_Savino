<?php

namespace Tests\Feature;

use App\Enums\CodiceDelRedirect;
use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\IndirizzoDaMappare;
use App\Models\Post;
use App\Models\Reindirizzamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I redirect del vecchio sito decisi a mano vincono sulle regole generali, e
 * i vecchi indirizzi senza destinazione finiscono nei «404 da mappare».
 */
class RedirectDecisiDalPannelloTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Parte da una tabella vuota: la migrazione dei 404 di Search Console
     * (14/10/2026) ne carica già alcuni.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Reindirizzamento::query()->delete();
    }

    private function redirect(string $da, ?string $verso, CodiceDelRedirect $codice = CodiceDelRedirect::Permanente): Reindirizzamento
    {
        return Reindirizzamento::create(['from_path' => $da, 'to_path' => $verso, 'status_code' => $codice]);
    }

    #[Test]
    public function il_redirect_deciso_vince_sulla_rotta_generica_dei_tag(): void
    {
        $redirect = $this->redirect('/tag/playasone', '/news/play-as-one');

        $this->get('/tag/playasone/')->assertStatus(301)->assertRedirect('/news/play-as-one');

        $redirect->refresh();
        $this->assertSame(1, $redirect->hits);
        $this->assertNotNull($redirect->last_hit_at);
        $this->assertDatabaseCount('redirect_mancanti', 0);
    }

    #[Test]
    public function il_redirect_deciso_vince_sul_permalink_automatico_delle_notizie(): void
    {
        Post::create([
            'title' => ['it' => 'Una notizia', 'en' => 'A news item'],
            'slug' => 'una-notizia-vecchia',
            'content' => ['it' => '<p>Testo</p>', 'en' => '<p>Text</p>'],
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);
        $this->redirect('/una-notizia-vecchia', 'https://www.legavolleyfemminile.it/');

        $this->get('/una-notizia-vecchia/')
            ->assertStatus(301)
            ->assertRedirect('https://www.legavolleyfemminile.it/');
    }

    #[Test]
    public function un_indirizzo_che_nessuna_rotta_conosce_trova_il_suo_redirect(): void
    {
        $this->redirect('/2019/05/una-vecchia-pagina', '/stagione');

        $this->get('/2019/05/una-vecchia-pagina/')->assertStatus(301)->assertRedirect('/stagione');
    }

    #[Test]
    public function un_410_dice_che_il_contenuto_non_c_e_piu(): void
    {
        $redirect = $this->redirect('/concorso-chiuso', null, CodiceDelRedirect::Rimosso);

        $this->get('/concorso-chiuso/')->assertStatus(410);
        $this->assertSame(1, $redirect->refresh()->hits);
    }

    #[Test]
    public function il_vecchio_indirizzo_si_salva_nella_forma_con_cui_si_cerca(): void
    {
        $redirect = $this->redirect('https://savinodelbenevolley.it/Tag/Coppa%20Italia/?utm_source=x', '/news');

        $this->assertSame('/tag/coppa italia', $redirect->from_path);
        $this->get('/TAG/coppa%20italia?ref=google')->assertStatus(301)->assertRedirect('/news');
    }

    #[Test]
    public function un_404_in_stile_wordpress_finisce_nei_404_da_mappare(): void
    {
        $this->get('/una-notizia-mai-importata/')->assertNotFound();
        $this->get('/una-notizia-mai-importata')->assertNotFound();

        $riga = IndirizzoDaMappare::sole();
        $this->assertSame('/una-notizia-mai-importata', $riga->percorso);
        $this->assertSame(IndirizzoDaMappare::ORIGINE_404, $riga->origine);
        $this->assertSame(2, $riga->hits);
    }

    #[Test]
    public function i_vecchi_indirizzi_dell_audit_seo_finiscono_nella_lista(): void
    {
        // I casi del controllo SEO del 15/10/2026: permalink di una notizia
        // mai importata, tag senza pagina, categoria di WordPress.
        $this->get('/sconfitta-indolore/')->assertNotFound();
        $this->get('/tag/una-parola-qualsiasi/')->assertStatus(301);
        $this->get('/category/news/')->assertNotFound();
        $this->get('/category/news/')->assertNotFound();

        $righe = IndirizzoDaMappare::query()->pluck('hits', 'percorso')->all();

        $this->assertSame([
            '/category/news' => 2,
            '/sconfitta-indolore' => 1,
            '/tag/una-parola-qualsiasi' => 1,
        ], collect($righe)->sortKeys()->all());
    }

    #[Test]
    public function le_sonde_e_i_404_qualsiasi_non_finiscono_nella_lista(): void
    {
        $this->get('/wp-login.php')->assertNotFound();
        $this->get('/wp-content/plugins/qualcosa/readme.txt')->assertNotFound();
        $this->get('/paginainesistente')->assertNotFound();

        $this->assertDatabaseCount('redirect_mancanti', 0);
    }

    #[Test]
    public function la_rotta_generica_del_vecchio_sito_conta_la_visita(): void
    {
        $this->get('/gallery/giornata-1-andata-2019/')->assertStatus(301)->assertRedirect('/gallery');

        $riga = IndirizzoDaMappare::sole();
        $this->assertSame('/gallery/giornata-1-andata-2019', $riga->percorso);
        $this->assertSame(IndirizzoDaMappare::ORIGINE_LEGACY, $riga->origine);
    }

    #[Test]
    public function creare_il_redirect_toglie_l_indirizzo_dai_404_da_mappare(): void
    {
        $this->get('/tag/scudetto/')->assertStatus(301);
        $this->assertDatabaseCount('redirect_mancanti', 1);

        $this->redirect('/tag/scudetto/', '/stagione/playoff');

        $this->assertDatabaseCount('redirect_mancanti', 0);
        $this->get('/tag/scudetto/')->assertRedirect('/stagione/playoff');
    }

    #[Test]
    public function il_vecchio_id_di_wordpress_senza_notizia_finisce_nella_lista_e_si_puo_mappare(): void
    {
        $this->get('/?p=12345')->assertOk();
        $this->assertSame('/?p=12345', IndirizzoDaMappare::sole()->percorso);

        $this->redirect('/?p=12345', '/news');
        // La home servita sopra è nella cache delle pagine (un minuto).
        Cache::flush();

        $this->get('/?p=12345')->assertStatus(301)->assertRedirect('/news');
    }

    #[Test]
    public function un_tag_con_lo_slug_di_una_categoria_porta_all_archivio_filtrato(): void
    {
        Category::create(['name' => ['it' => 'Challenge Cup', 'en' => 'Challenge Cup'], 'slug' => 'challenge-cup']);

        $this->get('/tag/challenge-cup/')->assertStatus(301)->assertRedirect('/news?categoria=challenge-cup');
        $this->get('/tag/una-parola-qualsiasi/')->assertStatus(301)->assertRedirect('/news');
    }

    #[Test]
    public function le_richieste_che_non_sono_navigazione_non_passano_dalla_tabella(): void
    {
        $this->redirect('/tag/playasone', '/news/play-as-one');

        $this->post('/tag/playasone')->assertStatus(405);
        $this->assertSame(0, Reindirizzamento::query()->where('from_path', '/tag/playasone')->sole()->hits);
    }
}

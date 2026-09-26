<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gli indirizzi del vecchio sito WordPress dopo il passaggio del dominio.
 *
 * Su WordPress le notizie stavano alla radice (`/titolo-della-notizia/`), qui
 * stanno sotto `/news/{slug}`: senza queste redirezioni ogni link indicizzato,
 * salvato da un aggregatore o condiviso sui social cadrebbe sulla rotta
 * generica delle pagine CMS e risponderebbe 404, pur essendo la notizia in
 * archivio.
 */
class PermalinkVecchioSitoTest extends TestCase
{
    use RefreshDatabase;

    private function notizia(string $slug, array $attributi = []): Post
    {
        return Post::create(array_merge([
            'title' => ['it' => 'Una notizia', 'en' => 'A news item'],
            'slug' => $slug,
            'content' => ['it' => '<p>Testo</p>', 'en' => '<p>Text</p>'],
            'excerpt' => ['it' => 'Sommario', 'en' => 'Summary'],
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ], $attributi));
    }

    #[Test]
    public function il_permalink_di_una_notizia_porta_alla_notizia(): void
    {
        $this->notizia('savino-del-bene-volley-e-claro-italia-insieme');

        $this->get('/savino-del-bene-volley-e-claro-italia-insieme')
            ->assertRedirect('/news/savino-del-bene-volley-e-claro-italia-insieme')
            ->assertStatus(301);
    }

    #[Test]
    public function la_barra_finale_di_wordpress_non_cambia_niente(): void
    {
        // WordPress pubblicava i permalink con la barra in fondo, ed e' quella
        // la forma che sta su Google e negli archivi degli aggregatori.
        $this->notizia('una-vittoria-importante');

        $this->get('/una-vittoria-importante/')
            ->assertRedirect('/news/una-vittoria-importante')
            ->assertStatus(301);
    }

    #[Test]
    public function una_bozza_non_viene_pubblicata_da_un_vecchio_indirizzo(): void
    {
        $this->notizia('comunicato-mai-uscito', ['status' => PostStatus::Draft]);

        $this->get('/comunicato-mai-uscito')->assertNotFound();
    }

    #[Test]
    public function una_notizia_programmata_resta_programmata(): void
    {
        $this->notizia('esce-domani', ['published_at' => now()->addWeek()]);

        $this->get('/esce-domani')->assertNotFound();
    }

    #[Test]
    public function un_permalink_senza_notizia_resta_404(): void
    {
        // I 2048 permalink dell'archivio 2014-2021 non importato: restano 404 e
        // non diventano un 301 di massa verso /news, che per Google e' un soft
        // 404 e in piu' nasconde il buco.
        $this->get('/una-notizia-del-2015-mai-importata')->assertNotFound();
    }

    #[Test]
    public function la_pagina_del_cms_vince_sulla_notizia_omonima(): void
    {
        // In produzione e' il caso di `cartelle-stampa`: pagina della sezione
        // Comunicazione e insieme vecchio comunicato. Vince la pagina, che e'
        // il contenuto vivo — e la rotta generica la porta alla sua sezione.
        Page::updateOrCreate(['slug' => 'cartelle-stampa'], [
            'title' => ['it' => 'Cartelle Stampa', 'en' => 'Press Kits'],
            'template' => 'Public/Comunicazione',
            'status' => PostStatus::Published,
        ]);
        $this->notizia('cartelle-stampa');

        $this->get('/cartelle-stampa')
            ->assertRedirect('/comunicazione/cartelle-stampa')
            ->assertStatus(301);
    }

    #[Test]
    public function le_rotte_di_sezione_non_cercano_le_notizie(): void
    {
        // `PageController@show` serve anche `/societa/{slug}` e le altre
        // sezioni: li' il vecchio sito non ha mai messo notizie, e un indirizzo
        // sbagliato deve restare tale.
        $this->notizia('una-notizia-qualunque');

        $this->get('/societa/una-notizia-qualunque')->assertNotFound();
        $this->get('/youth/una-notizia-qualunque')->assertNotFound();
    }

    #[Test]
    public function il_permalink_vale_anche_in_inglese(): void
    {
        $this->notizia('kiera-van-ryk-e-una-nuova-giocatrice');

        $this->get('/en/kiera-van-ryk-e-una-nuova-giocatrice')
            ->assertRedirect('/en/news/kiera-van-ryk-e-una-nuova-giocatrice')
            ->assertStatus(301);
    }

    #[Test]
    public function l_identificativo_wordpress_porta_alla_notizia(): void
    {
        // `/?p=41558` e' il guid che il vecchio feed pubblicava: chi l'ha
        // salvato come link arriva sulla home con quella query.
        $this->notizia('imma-sirressi-e-una-nuova-giocatrice', ['wp_id' => 41313]);

        $this->get('/?p=41313')
            ->assertRedirect('/news/imma-sirressi-e-una-nuova-giocatrice')
            ->assertStatus(301);
    }

    #[Test]
    public function un_identificativo_wordpress_sconosciuto_lascia_la_home(): void
    {
        $this->get('/?p=999999')->assertOk();
        $this->get('/')->assertOk();
    }

    #[Test]
    public function i_tag_portano_all_archivio_delle_notizie(): void
    {
        $this->get('/tag/playasone/')->assertRedirect('/news')->assertStatus(301);
    }

    #[Test]
    public function le_categorie_portano_all_archivio_gia_filtrato(): void
    {
        Category::create(['name' => ['it' => 'SDB Youth', 'en' => 'SDB Youth'], 'slug' => 'sdb-youth']);

        $this->get('/news-c/sdb-youth/')
            ->assertRedirect('/news?categoria=sdb-youth')
            ->assertStatus(301);
    }

    #[Test]
    public function una_categoria_annidata_si_riconosce_dall_ultimo_segmento(): void
    {
        // Sul vecchio sito le stagioni stavano sotto un contenitore:
        // `/news-c/archivi-notizie/serie-a1-2016-2017/`.
        Category::create([
            'name' => ['it' => 'Serie A1 2016-2017', 'en' => 'Serie A1 2016-2017'],
            'slug' => 'serie-a1-2016-2017',
        ]);

        $this->get('/news-c/archivi-notizie/serie-a1-2016-2017/')
            ->assertRedirect('/news?categoria=serie-a1-2016-2017')
            ->assertStatus(301);
    }

    #[Test]
    public function una_categoria_che_non_esiste_piu_porta_all_archivio(): void
    {
        $this->get('/news-c/sand-volley/')->assertRedirect('/news')->assertStatus(301);
    }

    #[Test]
    public function gli_album_e_le_schede_atleta_portano_alle_pagine_di_oggi(): void
    {
        $this->get('/gallery/giornata-1-andata-sabato-14-ottobre-2017/')
            ->assertRedirect('/gallery')
            ->assertStatus(301);
        $this->get('/giocatrice/cavalli/')->assertRedirect('/stagione')->assertStatus(301);
        $this->get('/archivi-partite/2019-2020/')
            ->assertRedirect('/stagione/risultati')
            ->assertStatus(301);
    }

    #[Test]
    public function le_rotte_vere_della_gallery_non_sono_oscurate(): void
    {
        // `/gallery/{any}` e' registrata dopo `/gallery/data` e
        // `/gallery/atleta/{slug}`: se l'ordine si invertisse, l'archivio
        // smetterebbe di caricare le foto oltre la prima schermata.
        $this->get('/gallery/data')->assertOk();
    }

    #[Test]
    public function le_pagine_del_vecchio_sito_hanno_un_erede(): void
    {
        $this->get('/video-gallery')->assertRedirect('/gallery')->assertStatus(301);
        $this->get('/informativa-privacy')->assertRedirect('/privacy-policy')->assertStatus(301);
        $this->get('/atlete-b1')->assertRedirect('/stagione/b1')->assertStatus(301);
        $this->get('/jam-camp')->assertRedirect('/summer-camp')->assertStatus(301);
        $this->get('/collaboratori')->assertRedirect('/societa/organigramma')->assertStatus(301);
        $this->get('/recruiting')->assertRedirect('/youth/settore-giovanile')->assertStatus(301);
        $this->get('/archivi-partite')->assertRedirect('/stagione/risultati')->assertStatus(301);
    }

    #[Test]
    public function il_pdf_dell_informativa_fornitori_porta_alla_pagina(): void
    {
        // Era un PDF nella libreria media di WordPress, linkato dal footer:
        // dal 26/09/2026 e' una pagina, e il vecchio indirizzo ci arriva.
        $this->get('/wp-content/uploads/2021/06/Informativa-Fornitori.pdf')
            ->assertRedirect('/informativa-fornitori')
            ->assertStatus(301);
    }

    #[Test]
    public function i_vecchi_indirizzi_del_feed_portano_al_feed(): void
    {
        // WordPress serviva lo stesso feed su piu' indirizzi, e i feed per
        // categoria e per tag stavano sotto quei prefissi: devono arrivare al
        // feed di oggi, non all'archivio HTML — chi legge un feed non saprebbe
        // che farsene di una pagina. Le rotte stanno prima di quelle di tag e
        // categoria, che altrimenti se li prenderebbero.
        $this->get('/feed/atom/')->assertRedirect('/feed')->assertStatus(301);
        $this->get('/feed/rss2/')->assertRedirect('/feed')->assertStatus(301);
        $this->get('/comments/feed')->assertRedirect('/feed')->assertStatus(301);
        $this->get('/news-c/sdb-youth/feed')->assertRedirect('/feed')->assertStatus(301);
        $this->get('/tag/playasone/feed')->assertRedirect('/feed')->assertStatus(301);
    }

    #[Test]
    public function il_feed_di_oggi_non_e_oscurato_dalle_rotte_legacy(): void
    {
        $this->get('/feed')->assertOk();
    }

    #[Test]
    public function gli_eventi_una_tantum_del_vecchio_sito_restano_404(): void
    {
        // Non hanno un erede: una convention del 2024 e un segnaposto della
        // redazione. Mandarli sulla home sarebbe un soft 404.
        $this->get('/health-perfomance-conference')->assertNotFound();
        $this->get('/pagina-in-aggiornamento')->assertNotFound();
    }

    #[Test]
    public function le_pagine_per_stagione_portano_alla_pagina_unica(): void
    {
        // Erano gia' in `sito.php` prima di questo lavoro: il file e' cambiato,
        // il comportamento no.
        $this->get('/classifica-2023-2024')->assertRedirect('/stagione/classifica')->assertStatus(301);
        $this->get('/campionato-2022-2023-andata')->assertRedirect('/stagione/risultati')->assertStatus(301);
        $this->get('/cev-champions-league-2025')->assertRedirect('/stagione/cev')->assertStatus(301);
        // Nuove: il vecchio sito aveva una pagina per ogni coppa e per ogni
        // playoff, qui c'e' una pagina sola per competizione.
        $this->get('/playoff-scudetto-2025-2026')->assertRedirect('/stagione/playoff')->assertStatus(301);
        $this->get('/coppa-italia-a1-2025')->assertRedirect('/stagione/coppa-italia')->assertStatus(301);
    }

    #[Test]
    public function una_pagina_del_cms_con_lo_stesso_slug_non_viene_intercettata(): void
    {
        // `biglietteria`, `organigramma`, `affiliazioni` esistono ancora nel
        // CMS: non stanno nella mappa legacy, ci pensa la rotta generica.
        Page::updateOrCreate(['slug' => 'organigramma'], [
            'title' => ['it' => 'Organigramma', 'en' => 'Organisation'],
            'template' => 'Public/Societa/Organigramma',
            'status' => PostStatus::Published,
        ]);

        $this->get('/organigramma')->assertRedirect('/societa/organigramma')->assertStatus(301);
    }
}

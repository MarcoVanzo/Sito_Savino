<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'archivio delle notizie si era fermato al 26 giugno mentre la redazione
 * continuava a pubblicare sul vecchio sito: tre mesi di comunicati mancanti,
 * che dal giorno del feed RSS sono anche quelli che la Lega non riceve.
 *
 * Qui si verifica che l'import li riprenda dalle API di WordPress senza
 * duplicare quello che c'e' gia', e senza lasciare in archivio link a un
 * dominio che sta per cambiare padrone.
 */
class ImportaLeNotizieDalVecchioSitoTest extends TestCase
{
    use RefreshDatabase;

    /** Un PNG vero di un pixel: la copertina si scarta se non e' un'immagine. */
    private function pixel(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    /**
     * @param  list<array<string, mixed>>  $comunicati
     * @param  list<array<string, mixed>>  $categorie
     * @param  list<array<string, mixed>>  $etichette
     */
    private function fingiIlVecchioSito(array $comunicati, array $categorie = [], array $etichette = []): void
    {
        Http::fake([
            '*/wp-json/wp/v2/posts*' => Http::response($comunicati),
            '*/wp-json/wp/v2/categories*' => Http::response($categorie),
            '*/wp-json/wp/v2/tags*' => Http::response($etichette),
            '*/wp-json/wp/v2/media/*' => Http::response([
                'source_url' => 'https://www.savinodelbenevolley.it/wp-content/uploads/2026/09/copertina.jpg',
            ]),
            'savinodelbenevolley.it/wp-content/uploads/*.pdf' => Http::response('%PDF-1.4 finto documento'),
            'savinodelbenevolley.it/wp-content/uploads/*' => Http::response($this->pixel(), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $sovrascritture
     * @return array<string, mixed>
     */
    private function comunicato(array $sovrascritture = []): array
    {
        return array_merge([
            'id' => 41558,
            'date' => '2026-09-22T14:00:43',
            'slug' => 'savino-del-bene-volley-e-claro-italia-insieme',
            'status' => 'publish',
            'title' => ['rendered' => 'Savino Del Bene Volley e Claro Italia insieme'],
            'content' => ['rendered' => '<p>La Savino Del Bene Volley annuncia la partnership.</p>'],
            'excerpt' => ['rendered' => '<p>La Savino Del Bene Volley annuncia la nuova partnership con Claro Italia, insegna specializzata nel settore ottico.</p>'],
            'categories' => [],
            'tags' => [],
            'featured_media' => 0,
            'yoast_head_json' => [
                'og_title' => 'Savino Del Bene Volley e Claro Italia insieme | Savino Del Bene Volley Scandicci',
                'og_description' => 'La nuova partnership per la stagione 2026-2027',
            ],
        ], $sovrascritture);
    }

    #[Test]
    public function importa_un_comunicato_che_non_abbiamo(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito([$this->comunicato()]);

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $notizia = Post::where('wp_id', 41558)->firstOrFail();

        $this->assertSame('savino-del-bene-volley-e-claro-italia-insieme', $notizia->slug);
        $this->assertSame('Savino Del Bene Volley e Claro Italia insieme', $notizia->getTranslation('title', 'it'));
        $this->assertSame('publish', $notizia->status->value);
        // L'ora e' quella locale del vecchio sito, come le 941 gia' in archivio.
        $this->assertSame('2026-09-22 14:00:43', $notizia->published_at->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('partnership', $notizia->getTranslation('excerpt', 'it'));
        $this->assertSame('La nuova partnership per la stagione 2026-2027', $notizia->getTranslation('meta_description', 'it'));
    }

    /**
     * La chiave naturale e' `wp_id`: e' quella che rende l'import ripetibile.
     */
    #[Test]
    public function rilanciarlo_non_duplica_niente(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito([$this->comunicato()]);

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();
        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertSame(1, Post::where('wp_id', 41558)->count());
    }

    /**
     * Il caso che avrebbe fatto piu' danno: "Serie A1 2026/2027" la redazione
     * l'aveva creata a mano, senza `wp_id` e con uno slug suo. Cercandola per
     * il solo `wp_id` sarebbe nata una seconda categoria con lo stesso nome, e
     * nove comunicati su quindici sarebbero finiti li' dentro invece che nella
     * categoria che sta prima nel menu.
     */
    #[Test]
    public function adotta_la_categoria_creata_a_mano_invece_di_duplicarla(): void
    {
        Storage::fake();

        $aMano = Category::create([
            'name' => ['it' => 'Serie A1 2026/2027'],
            'slug' => 'serie-a1-20262027',
            'sort_order' => 1,
        ]);

        $this->fingiIlVecchioSito(
            [$this->comunicato(['categories' => [2657]])],
            [['id' => 2657, 'name' => 'Serie A1 2026/2027', 'slug' => 'serie-a1-2026-2027']],
        );

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertSame(1, Category::count(), 'la categoria non deve essere duplicata');
        $this->assertSame(2657, $aMano->fresh()->wp_id, 'la riga esistente adotta l\'identificativo di WordPress');
        $this->assertSame([$aMano->id], Post::where('wp_id', 41558)->firstOrFail()->categories->pluck('id')->all());
    }

    /**
     * "News Sponsor" da noi si chiama `sponsor`: lo slug e' diverso, ma
     * l'identificativo di WordPress e' lo stesso.
     */
    #[Test]
    public function riconosce_la_categoria_rinominata_dal_pannello(): void
    {
        Storage::fake();

        $sponsor = Category::create(['wp_id' => 17, 'name' => ['it' => 'Sponsor'], 'slug' => 'sponsor']);

        $this->fingiIlVecchioSito(
            [$this->comunicato(['categories' => [17]])],
            [['id' => 17, 'name' => 'News Sponsor', 'slug' => 'news-sponsor']],
        );

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertSame(1, Category::count());
        $this->assertSame([$sponsor->id], Post::where('wp_id', 41558)->firstOrFail()->categories->pluck('id')->all());
    }

    /**
     * Su WordPress i comunicati senza categoria stanno in "Senza categoria":
     * da noi quella categoria e' "Notizie", ed e' cosi' che l'import di allora
     * l'ha ribattezzata.
     */
    #[Test]
    public function senza_categoria_diventa_notizie(): void
    {
        Storage::fake();

        $notizie = Category::create(['wp_id' => 1, 'name' => ['it' => 'Notizie'], 'slug' => 'notizie']);

        $this->fingiIlVecchioSito(
            [$this->comunicato(['categories' => [1]])],
            [['id' => 1, 'name' => 'Senza categoria', 'slug' => 'senza-categoria']],
        );

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertSame(1, Category::count());
        $this->assertSame([$notizie->id], Post::where('wp_id', 41558)->firstOrFail()->categories->pluck('id')->all());
    }

    /**
     * Quando in redazione si pubblica senza titolo WordPress lascia come slug
     * l'id del post: `41541-2` finirebbe nell'indirizzo della notizia e nel
     * feed che legge la Lega.
     */
    #[Test]
    public function sostituisce_lo_slug_generato_da_wordpress(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito([$this->comunicato([
            'id' => 41541,
            'slug' => '41541-2',
            'title' => ['rendered' => 'Nuova partnership con Città di Scandicci'],
        ])]);

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertSame(
            'nuova-partnership-con-citta-di-scandicci',
            Post::where('wp_id', 41541)->firstOrFail()->slug
        );
    }

    #[Test]
    public function scarica_l_immagine_di_copertina(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito([$this->comunicato(['featured_media' => 41560])]);

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $copertina = Post::where('wp_id', 41558)->firstOrFail()->getFirstMedia('cover');

        $this->assertNotNull($copertina, 'la notizia deve avere la copertina');
        $this->assertSame('copertina.jpg', $copertina->file_name);
    }

    /**
     * Il punto 3 del lavoro: una notizia non deve nascere con i link al vecchio
     * dominio, che si spegne alla migrazione.
     */
    #[Test]
    public function porta_sul_nostro_disco_i_media_citati_nel_testo(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito([$this->comunicato(['content' => ['rendered' => '<p>Il calendario: <a href="https://www.savinodelbenevolley.it/wp-content/uploads/2026/07/Calendario.pdf">scaricalo</a></p>'
            .'<p><img src="https://www.savinodelbenevolley.it/wp-content/uploads/2026/07/locandina.jpg" '
            .'srcset="https://www.savinodelbenevolley.it/wp-content/uploads/2026/07/locandina-300x200.jpg 300w" /></p>',
        ]])]);

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $contenuto = Post::where('wp_id', 41558)->firstOrFail()->getTranslation('content', 'it');

        $this->assertStringNotContainsString('savinodelbenevolley.it/wp-content', $contenuto);
        $this->assertStringContainsString('news/2026/07/Calendario.pdf', $contenuto);
        $this->assertStringContainsString('news/2026/07/locandina.jpg', $contenuto);
        $this->assertStringNotContainsString('srcset', $contenuto);
        Storage::assertExists('news/2026/07/Calendario.pdf');
        Storage::assertExists('news/2026/07/locandina.jpg');
    }

    /**
     * Le etichette generiche ripetono il nome della societa' o lo sport: sono
     * escluse dallo stesso filtro con cui sono entrate le 1626 in archivio.
     */
    #[Test]
    public function scarta_le_etichette_generiche_e_i_punteggi(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito(
            [$this->comunicato(['tags' => [42, 43, 44, 45]])],
            [],
            [
                ['id' => 42, 'name' => 'Savino Del Bene Volley', 'slug' => 'savino-del-bene-volley'],
                ['id' => 43, 'name' => '3-1', 'slug' => '3-1'],
                ['id' => 44, 'name' => '2026-2027', 'slug' => '2026-2027'],
                ['id' => 45, 'name' => 'Claro Italia', 'slug' => 'claro-italia'],
            ],
        );

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertSame(['claro-italia'], Tag::pluck('slug')->all());
        $this->assertSame(1, Post::where('wp_id', 41558)->firstOrFail()->tags()->count());
    }

    /**
     * La redazione puo' aver gia' scritto a mano lo stesso comunicato dal
     * pannello: la riga esistente si adotta invece di affiancarle un doppione
     * con lo slug numerato.
     */
    #[Test]
    public function adotta_la_notizia_gia_scritta_a_mano_con_lo_stesso_slug(): void
    {
        Storage::fake();

        $aMano = Post::factory()->create([
            'slug' => 'savino-del-bene-volley-e-claro-italia-insieme',
            'wp_id' => null,
        ]);

        $this->fingiIlVecchioSito([$this->comunicato()]);

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        $this->assertSame(1, Post::where('slug', 'like', 'savino-del-bene-volley-e-claro-italia%')->count());
        $this->assertSame(41558, $aMano->fresh()->wp_id);
    }

    /**
     * Senza `--da` si riparte dalla notizia piu' recente in archivio: e' cio'
     * che rende il comando un "riallinea" invece di un import una tantum.
     */
    #[Test]
    public function riparte_dall_ultima_notizia_in_archivio(): void
    {
        Storage::fake();
        Post::factory()->create(['published_at' => '2026-06-26 14:00:00']);
        $this->fingiIlVecchioSito([]);

        $this->artisan('news:importa-dal-vecchio-sito')->assertSuccessful();

        Http::assertSent(fn ($richiesta) => str_contains($richiesta->url(), 'after=2026-06-26T14%3A00%3A00')
            || str_contains($richiesta->url(), 'after=2026-06-26T14:00:00'));
    }

    #[Test]
    public function la_prova_non_scrive_niente(): void
    {
        Storage::fake();
        $this->fingiIlVecchioSito([$this->comunicato(['featured_media' => 41560])]);

        $this->artisan('news:importa-dal-vecchio-sito --prova')->assertSuccessful();

        $this->assertSame(0, Post::count());
        Storage::assertDirectoryEmpty('/');
    }

    /**
     * Il vecchio sito puo' non rispondere: meglio fermarsi dicendolo che
     * lasciare credere che l'archivio sia allineato.
     */
    #[Test]
    public function fallisce_se_il_vecchio_sito_non_risponde(): void
    {
        Storage::fake();
        Http::fake(['*/wp-json/wp/v2/posts*' => Http::response('', 503)]);

        $this->artisan('news:importa-dal-vecchio-sito')->assertFailed();

        $this->assertSame(0, Post::count());
    }
}

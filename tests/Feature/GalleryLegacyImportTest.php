<?php

namespace Tests\Feature;

use App\Models\GalleryEvent;
use App\Services\GalleryLegacy\LettoreGalleryVecchioSito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La Gallery del sito nuovo va presa dalla Gallery del vecchio.
 *
 * `gallery:from-posts` l'aveva costruita con le copertine dei comunicati
 * raggruppate per mese: sono altre foto, e la redazione lo ha segnalato. Le
 * vere stanno negli album di savinodelbenevolley.it/gallery/, che WordPress non
 * espone via API: si leggono dalle pagine pubbliche.
 *
 * Le fixture sono ritagli delle pagine vere (`tests/Fixtures/GalleryLegacy/`):
 * se il vecchio sito cambia, si aggiornano quelle e poi il lettore.
 */
class GalleryLegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $nome): string
    {
        return file_get_contents(base_path("tests/Fixtures/GalleryLegacy/{$nome}.html"));
    }

    /**
     * Il finto vecchio sito: l'indice risponde una volta sola, poi si svuota
     * (com'è alla fine della paginazione).
     */
    private function fingiIlVecchioSito(): void
    {
        Http::fake([
            'savinodelbenevolley.it/gallery/page/*' => Http::response('<html><body></body></html>'),
            'savinodelbenevolley.it/gallery/' => Http::response($this->fixture('indice')),
            'savinodelbenevolley.it/gallery/*' => Http::response($this->fixture('album')),
            'savinodelbenevolley.it/wp-content/*' => Http::response('finta-immagine-jpeg', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    #[Test]
    public function l_elenco_salta_il_feed_e_la_paginazione(): void
    {
        $this->fingiIlVecchioSito();

        $slug = app(LettoreGalleryVecchioSito::class)->elencoAlbum();

        $this->assertContains('festa-di-fine-stagione-2', $slug);
        $this->assertNotContains('feed', $slug);
        $this->assertNotContains('page', $slug);
    }

    /**
     * Le foto sono miniature 150x150: l'originale è lo stesso indirizzo senza
     * quel suffisso. Il logo dello sponsor in testata non è una foto dell'album.
     */
    #[Test]
    public function legge_titolo_data_e_foto_a_piena_risoluzione(): void
    {
        $this->fingiIlVecchioSito();

        $album = app(LettoreGalleryVecchioSito::class)->album('inizio-preparazione-2026-2027');

        $this->assertNotNull($album);
        $this->assertSame('2026-08-17', $album['data']);
        $this->assertStringNotContainsString('Savino Del Bene Volley Scandicci', $album['titolo']);

        foreach ($album['foto'] as $foto) {
            $this->assertStringNotContainsString('-150x150.', $foto);
        }

        $this->assertNotContains(
            'https://savinodelbenevolley.it/wp-content/uploads/2017/10/savino_title_sponsor.jpg',
            $album['foto']
        );
    }

    /** Le foto ripetute nella pagina non diventano due righe in archivio. */
    #[Test]
    public function le_foto_ripetute_contano_una_volta_sola(): void
    {
        $this->fingiIlVecchioSito();

        $album = app(LettoreGalleryVecchioSito::class)->album('inizio-preparazione-2026-2027');

        $this->assertSame(array_values(array_unique($album['foto'])), $album['foto']);
    }

    #[Test]
    public function l_importazione_crea_l_album_con_le_sue_foto(): void
    {
        $this->fingiIlVecchioSito();

        $this->artisan('gallery:importa-dal-vecchio-sito', ['--limite' => 1])->assertSuccessful();

        $evento = GalleryEvent::whereNotNull('legacy_slug')->first();

        $this->assertNotNull($evento);
        $this->assertTrue($evento->is_active);
        $this->assertGreaterThan(0, $evento->galleryImages()->count());
    }

    /**
     * Rilanciarlo prosegue con gli album successivi senza rifare i primi:
     * nessun album compare due volte e quelli già presi non cambiano.
     */
    #[Test]
    public function rilanciarla_prosegue_senza_duplicare(): void
    {
        $this->fingiIlVecchioSito();

        $this->artisan('gallery:importa-dal-vecchio-sito', ['--limite' => 2])->assertSuccessful();

        $primi = GalleryEvent::whereNotNull('legacy_slug')
            ->withCount('galleryImages')
            ->pluck('gallery_images_count', 'legacy_slug');

        $this->artisan('gallery:importa-dal-vecchio-sito', ['--limite' => 2])->assertSuccessful();

        $tutti = GalleryEvent::whereNotNull('legacy_slug')
            ->withCount('galleryImages')
            ->pluck('gallery_images_count', 'legacy_slug');

        $this->assertSame(
            $tutti->keys()->unique()->count(),
            $tutti->count(),
            'Lo stesso album è stato importato due volte.'
        );

        foreach ($primi as $slug => $quante) {
            $this->assertSame($quante, $tutti[$slug], "L'album {$slug} ha guadagnato foto alla seconda passata.");
        }
    }

    /** Un album già completo non si riapre nemmeno: è ciò che permette gli scaglioni. */
    #[Test]
    public function un_album_gia_fatto_non_viene_riscaricato(): void
    {
        $this->fingiIlVecchioSito();

        $this->artisan('gallery:importa-dal-vecchio-sito', ['--limite' => 1])->assertSuccessful();
        $fatto = GalleryEvent::whereNotNull('legacy_slug')->value('legacy_slug');

        $this->artisan('gallery:importa-dal-vecchio-sito', ['--limite' => 1])->assertSuccessful();

        $indirizzo = "https://savinodelbenevolley.it/gallery/{$fatto}/";
        $aperture = collect(Http::recorded())
            ->filter(fn (array $scambio) => $scambio[0]->url() === $indirizzo)
            ->count();

        $this->assertSame(1, $aperture, 'La pagina dell\'album è stata riaperta.');
    }

    /**
     * Gli album costruiti dai comunicati si tolgono; quelli fatti in redazione
     * restano dove sono.
     */
    #[Test]
    public function toglie_solo_gli_album_costruiti_dai_comunicati(): void
    {
        $this->fingiIlVecchioSito();

        $daiComunicati = GalleryEvent::create([
            'title' => ['it' => 'Marzo 2026 — News'],
            'event_date' => '2026-03-01',
            'category' => 'Partite',
            'description' => 'Foto dalle news di Marzo 2026',
            'is_active' => true,
        ]);

        $dellaRedazione = GalleryEvent::create([
            'title' => ['it' => 'Presentazione squadra'],
            'event_date' => '2026-09-01',
            'category' => 'Eventi',
            'description' => 'Album creato in redazione',
            'is_active' => true,
        ]);

        $this->artisan('gallery:importa-dal-vecchio-sito', [
            '--limite' => 1,
            '--togli-quelli-dai-comunicati' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('gallery_events', ['id' => $daiComunicati->id]);
        $this->assertDatabaseHas('gallery_events', ['id' => $dellaRedazione->id]);
    }
}

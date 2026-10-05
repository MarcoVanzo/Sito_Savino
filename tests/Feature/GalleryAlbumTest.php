<?php

namespace Tests\Feature;

use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ogni album della gallery ha un indirizzo suo (`/gallery/album/{id}-{titolo}`):
 * prima si apriva solo dentro `/gallery` e il link condiviso portava
 * all'elenco (richiesta della redazione del 05/10/2026).
 */
class GalleryAlbumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function album(string $titolo, int $foto): GalleryEvent
    {
        $evento = GalleryEvent::factory()->create([
            'title' => ['it' => $titolo, 'en' => $titolo],
            'event_date' => '2026-10-05',
            'is_active' => true,
        ]);

        GalleryImage::factory()->count($foto)->create([
            'gallery_event_id' => $evento->id,
            'category' => 'Partite',
            'is_active' => true,
        ]);

        return $evento;
    }

    #[Test]
    public function l_album_si_apre_dal_suo_indirizzo_con_le_sue_foto(): void
    {
        $giornata = $this->album('Giornata 1 - Serie A1', 3);
        $this->album('Presentazione squadra', 5);

        $this->get("/gallery/album/{$giornata->id}-giornata-1-serie-a1")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/Gallery')
                ->where('currentAlbum.id', $giornata->id)
                ->where('currentAlbum.name', 'Giornata 1 - Serie A1')
                ->where('currentAlbum.category', 'Partite')
                ->has('media', 3)
                ->where('media.0.event_id', $giornata->id)
                // L'archivio completo arriva dopo: la pagina deve saperlo.
                ->where('mediaTotal', 8));
    }

    #[Test]
    public function conta_solo_l_id_e_non_il_titolo(): void
    {
        $giornata = $this->album('Giornata 1 - Serie A1', 2);

        // Un titolo corretto dalla redazione non rompe i link già condivisi.
        $this->get("/gallery/album/{$giornata->id}-titolo-vecchio")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('currentAlbum.id', $giornata->id));
    }

    #[Test]
    public function un_album_inesistente_o_senza_foto_pubblicate_e_un_404(): void
    {
        $vuoto = GalleryEvent::factory()->create(['is_active' => true]);
        GalleryImage::factory()->create(['gallery_event_id' => $vuoto->id, 'is_active' => false]);

        $this->get("/gallery/album/{$vuoto->id}-vuoto")->assertNotFound();
        $this->get('/gallery/album/999999-non-esiste')->assertNotFound();
        $this->get('/gallery/album/niente')->assertNotFound();
    }

    #[Test]
    public function la_gallery_generale_non_ha_un_album_aperto(): void
    {
        $this->album('Giornata 1 - Serie A1', 2);

        $this->get('/gallery')->assertInertia(fn (AssertableInertia $page) => $page->where('currentAlbum', null));
    }

    #[Test]
    public function l_anteprima_social_porta_il_titolo_dell_album(): void
    {
        $giornata = $this->album('Giornata 1 - Serie A1', 2);

        $this->withHeaders(['User-Agent' => 'WhatsApp/2.23'])
            ->get("/gallery/album/{$giornata->id}-giornata-1-serie-a1")
            ->assertOk()
            ->assertSee('og:title" content="Giornata 1 - Serie A1', false);

        $this->withHeaders(['User-Agent' => 'WhatsApp/2.23'])
            ->get('/gallery/album/999999-non-esiste')
            ->assertNotFound();
    }
}

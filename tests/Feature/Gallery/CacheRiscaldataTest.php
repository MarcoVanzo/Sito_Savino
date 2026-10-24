<?php

namespace Tests\Feature\Gallery;

use App\Jobs\RicostruisciLaCacheDellaGallery;
use App\Models\GalleryImage;
use App\Models\Player;
use App\Services\GalleryArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'archivio della gallery in cache non si butta a ogni foto salvata: si
 * rigenera in coda, e intanto il visitatore trova la copia di prima.
 * Con dodicimila foto ricostruirlo costa una decina di secondi, che prima
 * pagava chi apriva la pagina subito dopo una modifica.
 */
class CacheRiscaldataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function salvare_una_foto_rigenera_l_archivio_in_coda_invece_di_buttarlo(): void
    {
        Bus::fake([RicostruisciLaCacheDellaGallery::class]);
        Cache::put(GalleryArchive::CHIAVE.':it', [['id' => 1]], now()->addDay());

        GalleryImage::factory()->create(['is_active' => true]);

        Bus::assertDispatched(RicostruisciLaCacheDellaGallery::class);
        $this->assertSame([['id' => 1]], Cache::get(GalleryArchive::CHIAVE.':it'), 'la copia in cache deve restare finché il job non la sostituisce');
    }

    #[Test]
    public function il_job_sostituisce_la_copia_in_cache_per_ogni_lingua(): void
    {
        Bus::fake([RicostruisciLaCacheDellaGallery::class]);
        GalleryImage::factory()->count(3)->create(['is_active' => true]);
        GalleryImage::factory()->create(['is_active' => false]);
        Cache::put(GalleryArchive::CHIAVE.':it', [], now()->addDay());

        (new RicostruisciLaCacheDellaGallery)->handle(app(GalleryArchive::class));

        foreach (config('app.supported_locales') as $locale) {
            $this->assertCount(3, (new GalleryArchive)->media(null, $locale));
        }
    }

    #[Test]
    public function l_archivio_sta_in_cache_compresso_e_si_rilegge_uguale(): void
    {
        GalleryImage::factory()->count(3)->create(['is_active' => true]);
        $archivio = new GalleryArchive;
        $archivio->riscalda();

        // Serializzato pesava 8 MB in una colonna mediumtext da 16: senza
        // compressione, con la gallery che cresce, la scrittura falliva.
        $grezzo = Cache::get(GalleryArchive::CHIAVE.':it');
        $this->assertIsString($grezzo);
        $this->assertStringStartsWith('gz:', $grezzo);
        $this->assertSame($archivio->costruisci(null, 'it'), (new GalleryArchive)->media(null, 'it'));
    }

    #[Test]
    public function la_copia_scritta_prima_della_compressione_resta_valida(): void
    {
        Cache::put(GalleryArchive::CHIAVE.':it', [['id' => 1]], now()->addDay());

        $this->assertSame([['id' => 1]], (new GalleryArchive)->media(null, 'it'));
    }

    #[Test]
    public function una_copia_illeggibile_si_ricostruisce(): void
    {
        GalleryImage::factory()->count(2)->create(['is_active' => true]);
        Cache::put(GalleryArchive::CHIAVE.':it', 'gz:non-base64!', now()->addDay());

        $this->assertCount(2, (new GalleryArchive)->media(null, 'it'));
        $this->assertStringStartsWith('gz:', Cache::get(GalleryArchive::CHIAVE.':it'));
    }

    #[Test]
    public function la_variante_per_atleta_si_butta_come_prima(): void
    {
        Bus::fake([RicostruisciLaCacheDellaGallery::class]);
        $foto = GalleryImage::factory()->create(['is_active' => true]);
        $atleta = Player::factory()->create();
        $archivio = app(GalleryArchive::class);
        Cache::put($archivio->chiave($atleta, 'it'), [['id' => 1]], now()->addDay());

        $foto->update(['category' => 'Eventi']);

        // Si butta cambiando generazione: la copia vecchia non si trova più.
        $this->assertNull(Cache::get($archivio->chiave($atleta, 'it')));
        $this->assertSame([], $archivio->media($atleta, 'it'));
    }

    #[Test]
    public function il_comando_di_avvio_mette_in_coda_la_ricostruzione(): void
    {
        Bus::fake([RicostruisciLaCacheDellaGallery::class]);

        $this->artisan('gallery:riscalda-cache')->assertSuccessful();

        Bus::assertDispatched(RicostruisciLaCacheDellaGallery::class);
    }
}

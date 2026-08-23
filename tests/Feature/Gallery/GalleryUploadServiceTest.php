<?php

namespace Tests\Feature\Gallery;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Services\GalleryUploadService;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il caricamento delle foto in un album.
 *
 * Due comportamenti reggono tutto il resto: ogni foto eredita dall'album i dati
 * di pubblicazione — così una foto non finisce online prima dell'album che la
 * contiene — e la stessa immagine caricata due volte non diventa due righe.
 * L'impronta è un SHA-256 del contenuto, quindi riconosce il doppione anche se
 * il file è stato rinominato.
 */
class GalleryUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('public');
    }

    private function fileSuDisco(string $nome, string $contenuto): string
    {
        Storage::disk('public')->put($nome, $contenuto);

        return $nome;
    }

    /**
     * Il servizio usa del componente solo il nome del disco e lo svuotamento
     * finale. Un FileUpload vero pretende un container di form e una richiesta
     * Livewire: qui basta un doppio che risponda a quei due metodi.
     */
    private function componente(): FileUpload
    {
        $componente = Mockery::mock(FileUpload::class);
        $componente->shouldReceive('getDiskName')->andReturn('public');
        $componente->shouldReceive('state')->andReturnSelf();

        return $componente;
    }

    #[Test]
    public function l_impronta_dipende_dal_contenuto_non_dal_nome(): void
    {
        $primo = $this->fileSuDisco('una.jpg', 'stessa immagine');
        $secondo = $this->fileSuDisco('un-altro-nome.jpg', 'stessa immagine');
        $diverso = $this->fileSuDisco('terza.jpg', 'immagine diversa');

        $a = GalleryUploadService::computeFileHash($primo, 'public');
        $b = GalleryUploadService::computeFileHash($secondo, 'public');
        $c = GalleryUploadService::computeFileHash($diverso, 'public');

        $this->assertNotNull($a);
        $this->assertSame($a, $b, 'Stesso contenuto, stessa impronta.');
        $this->assertNotSame($a, $c);
    }

    #[Test]
    public function un_file_che_non_esiste_non_ha_impronta(): void
    {
        $this->assertNull(GalleryUploadService::computeFileHash('mai-caricato.jpg', 'public'));
    }

    #[Test]
    public function il_doppione_si_riconosce_dall_impronta_gia_in_archivio(): void
    {
        $evento = GalleryEvent::factory()->create();
        GalleryImage::create([
            'gallery_event_id' => $evento->id,
            'title' => 'Gia presente',
            'file_hash' => 'impronta-nota',
        ]);

        $this->assertTrue(GalleryUploadService::isDuplicate('impronta-nota'));
        $this->assertFalse(GalleryUploadService::isDuplicate('impronta-mai-vista'));
    }

    #[Test]
    public function ogni_foto_caricata_diventa_una_riga_e_accoda_l_analisi(): void
    {
        $evento = GalleryEvent::factory()->create(['is_active' => true]);
        $file = $this->fileSuDisco('gara.jpg', 'contenuto della foto');

        GalleryUploadService::processUploads($this->componente(), [$file], $evento);

        $this->assertDatabaseCount('gallery_images', 1);
        Queue::assertPushed(AnalyzeGalleryImageJob::class, 1);
    }

    /**
     * La foto eredita titolo, categoria e stato di pubblicazione dall'album:
     * un album ancora in bozza non deve mostrare foto online.
     */
    #[Test]
    public function la_foto_eredita_i_dati_dall_album(): void
    {
        $evento = GalleryEvent::factory()->create([
            'title' => 'Savino - Milano',
            'category' => 'partite',
            'is_active' => false,
        ]);

        GalleryUploadService::processUploads(
            $this->componente(),
            [$this->fileSuDisco('gara.jpg', 'contenuto')],
            $evento
        );

        $foto = GalleryImage::first();
        $this->assertSame('Savino - Milano', $foto->title);
        $this->assertSame('partite', $foto->category);
        $this->assertFalse((bool) $foto->is_active);
        $this->assertSame($evento->id, $foto->gallery_event_id);
    }

    #[Test]
    public function la_stessa_immagine_caricata_due_volte_resta_una_sola_riga(): void
    {
        $evento = GalleryEvent::factory()->create();

        GalleryUploadService::processUploads(
            $this->componente(),
            [$this->fileSuDisco('prima.jpg', 'contenuto identico')],
            $evento
        );

        GalleryUploadService::processUploads(
            $this->componente(),
            [$this->fileSuDisco('seconda.jpg', 'contenuto identico')],
            $evento
        );

        $this->assertDatabaseCount('gallery_images', 1);
        Queue::assertPushed(AnalyzeGalleryImageJob::class, 1);
    }

    #[Test]
    public function il_doppione_dentro_lo_stesso_caricamento_viene_saltato(): void
    {
        $evento = GalleryEvent::factory()->create();

        GalleryUploadService::processUploads($this->componente(), [
            $this->fileSuDisco('a.jpg', 'uguale'),
            $this->fileSuDisco('b.jpg', 'uguale'),
            $this->fileSuDisco('c.jpg', 'diversa'),
        ], $evento);

        $this->assertDatabaseCount('gallery_images', 2);
        Queue::assertPushed(AnalyzeGalleryImageJob::class, 2);
    }

    #[Test]
    public function senza_file_non_succede_niente(): void
    {
        $evento = GalleryEvent::factory()->create();

        GalleryUploadService::processUploads($this->componente(), [], $evento);
        GalleryUploadService::processUploads($this->componente(), null, $evento);

        $this->assertDatabaseCount('gallery_images', 0);
        Queue::assertNothingPushed();
    }

    /**
     * Il componente si svuota dopo il caricamento: senza, il salvataggio
     * successivo del form ricaricherebbe le stesse foto.
     */
    #[Test]
    public function il_campo_si_svuota_dopo_il_caricamento(): void
    {
        $evento = GalleryEvent::factory()->create();

        $componente = Mockery::mock(FileUpload::class);
        $componente->shouldReceive('getDiskName')->andReturn('public');
        $componente->shouldReceive('state')->once()->with([])->andReturnSelf();

        GalleryUploadService::processUploads(
            $componente,
            [$this->fileSuDisco('gara.jpg', 'contenuto')],
            $evento
        );

        $componente->shouldHaveReceived('state')->with([]);
    }
}

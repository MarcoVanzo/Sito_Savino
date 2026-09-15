<?php

namespace Tests\Feature\Console;

use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il giro orario che manda all'AI le foto mai analizzate.
 *
 * L'import dell'archivio storico ha portato undicimila foto senza passare
 * dall'upload del pannello: nessuna era mai arrivata a CompreFace e il filtro
 * per atleta della gallery pubblica offriva cinque nomi. Il comando deve
 * prendere solo quelle, a blocchi, senza rimettere in coda ciò che il worker
 * non ha ancora smaltito.
 */
class AnalyzeGalleryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    private function fotoConFile(array $attributi = []): GalleryImage
    {
        $foto = GalleryImage::factory()->create($attributi);
        $foto->addMedia(UploadedFile::fake()->image('foto.jpg', 40, 40))->toMediaCollection('gallery', 'public');

        return $foto;
    }

    #[Test]
    public function pending_accoda_solo_le_foto_mai_analizzate(): void
    {
        $this->fotoConFile(['ai_analyzed_at' => now()]);
        $daFare = $this->fotoConFile();

        $this->artisan('gallery:analyze', ['--pending' => true, '--force' => true])
            ->expectsOutputToContain('Foto da analizzare: 1')
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeGalleryImageJob::class, 1);
        Queue::assertPushed(AnalyzeGalleryImageJob::class, fn (AnalyzeGalleryImageJob $job) => $job->galleryImage->is($daFare));
    }

    #[Test]
    public function una_foto_senza_file_non_si_manda_all_ai(): void
    {
        GalleryImage::factory()->create();

        $this->artisan('gallery:analyze', ['--pending' => true, '--force' => true])
            ->expectsOutputToContain('Nessuna foto trovata')
            ->assertSuccessful();

        Queue::assertNotPushed(AnalyzeGalleryImageJob::class);
    }

    #[Test]
    public function il_limite_prende_le_foto_piu_vecchie_per_prime(): void
    {
        $prima = $this->fotoConFile();
        $seconda = $this->fotoConFile();
        $this->fotoConFile();

        $this->artisan('gallery:analyze', ['--pending' => true, '--limit' => 2, '--force' => true])
            ->expectsOutputToContain('Foto da analizzare: 2')
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeGalleryImageJob::class, 2);
        Queue::assertPushed(AnalyzeGalleryImageJob::class, fn (AnalyzeGalleryImageJob $job) => $job->galleryImage->is($prima));
        Queue::assertPushed(AnalyzeGalleryImageJob::class, fn (AnalyzeGalleryImageJob $job) => $job->galleryImage->is($seconda));
    }

    #[Test]
    public function il_limite_tiene_conto_dei_job_gia_in_coda(): void
    {
        $this->fotoConFile();
        $this->fotoConFile();
        $this->fotoConFile();

        // Due job "ai" che il worker non ha ancora preso: con --limit=3 ne
        // entra uno solo, e con --limit=2 nessuno.
        foreach (range(1, 2) as $i) {
            DB::table('jobs')->insert([
                'queue' => 'ai',
                'payload' => '{}',
                'attempts' => 0,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ]);
        }

        $this->artisan('gallery:analyze', ['--pending' => true, '--limit' => 3, '--force' => true])
            ->expectsOutputToContain('Foto da analizzare: 1')
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeGalleryImageJob::class, 1);

        $this->artisan('gallery:analyze', ['--pending' => true, '--limit' => 2, '--force' => true])
            ->expectsOutputToContain('già piena')
            ->assertSuccessful();

        Queue::assertPushed(AnalyzeGalleryImageJob::class, 1);
    }

    #[Test]
    public function senza_filtro_il_comando_si_rifiuta(): void
    {
        $this->artisan('gallery:analyze', ['--force' => true])
            ->expectsOutputToContain('--pending')
            ->assertFailed();

        Queue::assertNotPushed(AnalyzeGalleryImageJob::class);
    }
}

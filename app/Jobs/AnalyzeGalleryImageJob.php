<?php

namespace App\Jobs;

use App\Exceptions\MediaProcessingException;
use App\Models\GalleryImage;
use App\Models\Player;
use App\Services\FacialRecognitionService;
use App\Support\StagioniDelleAtlete;
use App\Support\TestiSeoDellaFoto;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnalyzeGalleryImageJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Se il modello GalleryImage viene eliminato prima che il job venga processato,
     * il job viene automaticamente scartato invece di lanciare ModelNotFoundException.
     */
    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    public int $timeout = 120;

    public int $maxExceptions = 2;

    public array $backoff = [30, 60];

    public function __construct(
        public GalleryImage $galleryImage
    ) {
        $this->onQueue('ai');
    }

    public function handle(FacialRecognitionService $facialRecognitionService): void
    {
        $imageId = $this->galleryImage->id;
        $startTime = microtime(true);

        Log::info("AnalyzeGalleryImageJob: [START] Image #{$imageId}", [
            'attempt' => $this->attempts(),
            'batch_id' => $this->batch()?->id,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // Skip if batch was cancelled
        if ($this->batch() && $this->batch()->cancelled()) {
            Log::info("AnalyzeGalleryImageJob: [SKIP] Image #{$imageId} — batch cancelled");

            return;
        }

        // Get the image file path from Spatie Media Library
        Log::info("AnalyzeGalleryImageJob: [STEP 1/6] Image #{$imageId} — Loading media");
        $media = $this->galleryImage->getFirstMedia('gallery');

        if (! $media) {
            Log::warning("AnalyzeGalleryImageJob: [SKIP] Image #{$imageId} — no media found");

            return;
        }

        Log::info("AnalyzeGalleryImageJob: [STEP 2/6] Image #{$imageId} — Checking S3 file", [
            'disk' => $media->disk,
            'file' => $media->file_name,
            'size_kb' => round($media->size / 1024, 1),
        ]);

        // Use Storage facade for S3 compatibility (getPath() only works with local disk)
        $disk = Storage::disk($media->disk);
        $relativePath = $media->getPathRelativeToRoot();

        if (! $disk->exists($relativePath)) {
            Log::warning("AnalyzeGalleryImageJob: [FAIL] Image #{$imageId} — File not found on disk {$media->disk}: {$relativePath}");

            return;
        }

        // Download to a temporary file for CompreFace (which needs a local file path)
        $tempPath = sys_get_temp_dir().'/'.uniqid('gallery_').'_'.$media->file_name;

        try {
            Log::info("AnalyzeGalleryImageJob: [STEP 3/6] Image #{$imageId} — Streaming from S3 to temp");
            $downloadStart = microtime(true);

            // Usa get() invece di readStream per evitare problemi di timeout con DigitalOcean Spaces
            $fileContent = $disk->get($relativePath);
            if ($fileContent === null) {
                throw new MediaProcessingException("Failed to read file from S3: {$relativePath}");
            }

            if (file_put_contents($tempPath, $fileContent) === false) {
                throw new MediaProcessingException("Failed to write to temp file: {$tempPath}");
            }

            // Libera la memoria subito dopo la scrittura su disco
            unset($fileContent);

            $downloadMs = round((microtime(true) - $downloadStart) * 1000);
            $fileSizeKb = round(filesize($tempPath) / 1024, 1);
            Log::info("AnalyzeGalleryImageJob: [STEP 3/6] Image #{$imageId} — Streamed {$fileSizeKb}KB in {$downloadMs}ms", [
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            Log::info("AnalyzeGalleryImageJob: [STEP 4/6] Image #{$imageId} — Calling CompreFace recognize");
            $recognizeStart = microtime(true);
            $analysisResult = $facialRecognitionService->recognizeFaces($tempPath);
            $recognizeMs = round((microtime(true) - $recognizeStart) * 1000);
            $detectedPersons = $analysisResult['detected_persons'] ?? [];
            $hasUnrecognizedFaces = $analysisResult['has_unrecognized_faces'] ?? false;

            Log::info("AnalyzeGalleryImageJob: [STEP 4/6] Image #{$imageId} — CompreFace responded in {$recognizeMs}ms", [
                'detected_count' => count($detectedPersons),
                'has_unrecognized' => $hasUnrecognizedFaces,
            ]);

            // Un'atleta riconosciuta su una foto di una stagione in cui giocava
            // altrove è uno scambio di volto, non un tag (StagioniDelleAtlete).
            $dataDellaFoto = $this->galleryImage->galleryEvent?->event_date;
            $detectedPersons = array_values(array_filter($detectedPersons, function (array $detected) use ($dataDellaFoto, $imageId) {
                if ($detected['person_type'] !== Player::class) {
                    return true;
                }

                $atleta = Player::find($detected['person_id']);

                if ($atleta && StagioniDelleAtlete::eraInSquadra($atleta, $dataDellaFoto) === false) {
                    Log::info("AnalyzeGalleryImageJob: Image #{$imageId} — tag scartato, {$atleta->full_name} non era in squadra");

                    return false;
                }

                return true;
            }));

            if (! empty($detectedPersons)) {
                foreach ($detectedPersons as $detected) {
                    // Inserisci nella tabella polimorfica gallery_image_person
                    DB::table('gallery_image_person')->updateOrInsert(
                        [
                            'gallery_image_id' => $this->galleryImage->id,
                            'person_type' => $detected['person_type'],
                            'person_id' => $detected['person_id'],
                        ],
                        [
                            'confidence_score' => $detected['confidence'],
                            'updated_at' => now(),
                        ]
                    );

                    // Imposta created_at solo per nuovi record (updateOrInsert non lo distingue)
                    DB::table('gallery_image_person')
                        ->where('gallery_image_id', $this->galleryImage->id)
                        ->where('person_type', $detected['person_type'])
                        ->where('person_id', $detected['person_id'])
                        ->whereNull('created_at')
                        ->update(['created_at' => now()]);
                }
            }

            Log::info("AnalyzeGalleryImageJob: [STEP 5/6] Image #{$imageId} — Updating flags");

            // Il flag lo decide l'analisi, in entrambe le direzioni: prima si
            // accendeva soltanto, e una foto importata già marcata restava
            // "da rivedere" per sempre anche quando l'AI non trovava nulla.
            $this->galleryImage->needs_review = $hasUnrecognizedFaces;

            // Segna la foto come analizzata dall'AI
            $this->galleryImage->ai_analyzed_at = now();

            // Ottimizza SEO (titolo, alt text, meta) — esegue saveQuietly() internamente
            Log::info("AnalyzeGalleryImageJob: [STEP 6/6] Image #{$imageId} — SEO optimization");
            $this->optimizeForSeo();

            $totalMs = round((microtime(true) - $startTime) * 1000);
            Log::info("AnalyzeGalleryImageJob: [DONE] Image #{$imageId} completed in {$totalMs}ms", [
                'detected_persons' => count($detectedPersons),
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);
        } catch (\Throwable $e) {
            $totalMs = round((microtime(true) - $startTime) * 1000);
            Log::error("AnalyzeGalleryImageJob: [ERROR] Image #{$imageId} failed after {$totalMs}ms", [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            throw $e; // Re-throw to let the batch system handle retries
        } finally {
            // Cleanup: always delete the temporary file
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Ottimizza la foto per la SEO dopo il riconoscimento facciale.
     * Genera: titolo descrittivo, alt text, custom properties sulla media.
     * Include sia Player che StaffMember riconosciuti. Le regole stanno in
     * TestiSeoDellaFoto, condivise con la revoca del riconoscimento.
     */
    protected function optimizeForSeo(): void
    {
        TestiSeoDellaFoto::applica($this->galleryImage);

        // Unico save del modello GalleryImage (include needs_review se settato)
        $this->galleryImage->saveQuietly();
    }

    /**
     * Handle a job failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeGalleryImageJob definitively failed', [
            'gallery_image_id' => $this->galleryImage->id,
            'error' => $exception->getMessage(),
        ]);

        // Flag the image for manual review since AI couldn't process it
        $this->galleryImage->needs_review = true;
        $this->galleryImage->saveQuietly();
    }
}

<?php

namespace App\Jobs;

use App\Models\GalleryImage;
use App\Services\FacialRecognitionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnalyzeGalleryImageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $maxExceptions = 2;

    public array $backoff = [30, 60];

    public function __construct(
        public GalleryImage $galleryImage
    ) {}

    public function handle(FacialRecognitionService $facialRecognitionService): void
    {
        // Get the image file path from Spatie Media Library
        $media = $this->galleryImage->getFirstMedia('gallery');

        if (! $media) {
            return;
        }

        // Use Storage facade for S3 compatibility (getPath() only works with local disk)
        $disk = Storage::disk($media->disk);
        $relativePath = $media->getPathRelativeToRoot();

        if (! $disk->exists($relativePath)) {
            Log::warning("AnalyzeGalleryImageJob: File not found on disk {$media->disk}: {$relativePath}");

            return;
        }

        // Download to a temporary file for CompreFace (which needs a local file path)
        $tempPath = sys_get_temp_dir().'/'.uniqid('gallery_').'_'.$media->file_name;

        try {
            file_put_contents($tempPath, $disk->get($relativePath));

            $analysisResult = $facialRecognitionService->recognizeFaces($tempPath);
            $detectedPlayers = $analysisResult['detected_players'] ?? [];
            $hasUnrecognizedFaces = $analysisResult['has_unrecognized_faces'] ?? false;

            $needsReview = $hasUnrecognizedFaces;

            if (! empty($detectedPlayers)) {
                $syncData = [];
                foreach ($detectedPlayers as $detected) {
                    $syncData[$detected['player_id']] = ['confidence_score' => $detected['confidence']];
                }

                // Sync players without detaching existing ones
                $this->galleryImage->players()->syncWithoutDetaching($syncData);
            }

            // Ottimizza SEO (titolo, alt text, meta) anche senza atlete riconosciute
            $this->optimizeForSeo();

            // If there are unrecognized faces, flag the image for manual review
            if ($needsReview) {
                $this->galleryImage->needs_review = true;
                $this->galleryImage->saveQuietly();
            }
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
     */
    protected function optimizeForSeo(): void
    {
        $this->galleryImage->loadMissing(['players', 'galleryEvent']);
        $players = $this->galleryImage->players;
        $event = $this->galleryImage->galleryEvent;

        // Costruisci i nomi delle atlete
        $playerNames = $players->map->full_name->toArray();
        $playerLastNames = $players->map->last_name->toArray();
        $namesString = implode(', ', $playerNames);
        $lastNamesString = implode(', ', $playerLastNames);

        // Contesto dell'evento
        $eventTitle = $event?->title ?? '';
        $category = $this->galleryImage->category ?? $event?->category ?? 'Partite';
        $eventDate = $event?->event_date?->format('d/m/Y') ?? '';

        // --- 1. Titolo SEO strutturato ---
        // Formato: "Antropova, Mingardi - Partita vs Busto Arsizio 01/07/2026"
        // o solo: "Partita vs Busto Arsizio 01/07/2026" se nessuna atleta
        $titleParts = [];

        if (! empty($playerNames)) {
            $titleParts[] = $namesString;
        }

        if (! empty($eventTitle)) {
            $titleParts[] = $eventTitle;
        }

        if (! empty($eventDate)) {
            $titleParts[] = $eventDate;
        }

        $seoTitle = implode(' - ', $titleParts);

        if (! empty($seoTitle)) {
            $this->galleryImage->title = $seoTitle;
        }

        // --- 2. Alt text sulla media Spatie ---
        $media = $this->galleryImage->getFirstMedia('gallery');
        if ($media) {
            // Alt text descrittivo per Google Images
            $altParts = ['Savino Del Bene Volley'];
            if (! empty($playerNames)) {
                $altParts[] = $namesString;
            }
            if (! empty($eventTitle)) {
                $altParts[] = $eventTitle;
            }
            $altText = implode(' - ', $altParts);

            $media->setCustomProperty('alt', $altText);

            // Description per SEO
            $description = 'Foto ';
            if (! empty($playerNames)) {
                $description .= 'di ' . $namesString . ' ';
            }
            $description .= 'della Savino Del Bene Volley';
            if (! empty($eventTitle)) {
                $description .= ' durante ' . $eventTitle;
            }
            if (! empty($eventDate)) {
                $description .= ' (' . $eventDate . ')';
            }
            $media->setCustomProperty('description', $description);

            // Keywords per ricerca interna
            $keywords = array_merge(
                ['Savino Del Bene', 'Volley', 'Serie A'],
                $playerLastNames,
                [$category]
            );
            $media->setCustomProperty('keywords', implode(', ', $keywords));

            $media->save();
        }

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

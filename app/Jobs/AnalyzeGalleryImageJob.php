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

                // Update title for SEO if not already containing player names
                $this->updateTitleWithPlayerNames();
            }

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

    protected function updateTitleWithPlayerNames(): void
    {
        $this->galleryImage->loadMissing('players');
        $playerNames = $this->galleryImage->players->map->full_name->toArray();

        if (empty($playerNames)) {
            return;
        }

        $namesString = implode(', ', $playerNames);
        $currentTitle = $this->galleryImage->title ?? '';

        // If title doesn't contain the names, append them
        $needsUpdate = false;
        foreach ($playerNames as $name) {
            if (stripos($currentTitle, $name) === false) {
                $needsUpdate = true;
                break;
            }
        }

        if ($needsUpdate) {
            if (empty($currentTitle)) {
                $this->galleryImage->title = 'Foto con '.$namesString;
            } else {
                $this->galleryImage->title = $currentTitle.' - con '.$namesString;
            }
            // Temporarily disable activity log to avoid spamming logs for an automatic background process
            $this->galleryImage->saveQuietly();
        }
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

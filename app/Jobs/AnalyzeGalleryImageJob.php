<?php

namespace App\Jobs;

use App\Models\GalleryImage;
use App\Services\FacialRecognitionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeGalleryImageJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public GalleryImage $galleryImage
    ) {}

    public function handle(FacialRecognitionService $facialRecognitionService): void
    {
        // Get the image file path from Spatie Media Library
        $media = $this->galleryImage->getFirstMedia('gallery');
        
        if (!$media) {
            return;
        }

        $imagePath = $media->getPath();

        if (!file_exists($imagePath)) {
            Log::warning("AnalyzeGalleryImageJob: File not found at {$imagePath}");
            return;
        }

        $analysisResult = $facialRecognitionService->recognizeFaces($imagePath);
        $detectedPlayers = $analysisResult['detected_players'] ?? [];
        $hasUnrecognizedFaces = $analysisResult['has_unrecognized_faces'] ?? false;

        $needsReview = $hasUnrecognizedFaces;

        if (!empty($detectedPlayers)) {
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
                $this->galleryImage->title = 'Foto con ' . $namesString;
            } else {
                $this->galleryImage->title = $currentTitle . ' - con ' . $namesString;
            }
            // Temporarily disable activity log to avoid spamming logs for an automatic background process
            $this->galleryImage->saveQuietly();
        }
    }
}

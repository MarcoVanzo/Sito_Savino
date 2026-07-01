<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FacialRecognitionService
{
    protected string $host;

    protected string $apiKey;

    public function __construct()
    {
        $this->host = config('services.compreface.host', 'http://localhost:8000');
        $this->apiKey = config('services.compreface.key', '');
    }

    /**
     * Get the base URL for the API.
     */
    protected function getBaseUrl(): string
    {
        return rtrim($this->host, '/').'/api/v1/recognition';
    }

    /**
     * Create a subject for the player in CompreFace.
     */
    public function createSubject(Player $player): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        $subjectName = $this->getSubjectName($player);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->timeout(15)->retry(2, 1000, throw: false)->post($this->getBaseUrl().'/subjects', [
            'subject' => $subjectName,
        ]);

        if ($response->successful() || $response->status() === 400) {
            // 400 usually means it already exists, we consider it success for our flow
            return true;
        }

        Log::error('CompreFace Create Subject Error: '.$response->body());

        return false;
    }

    /**
     * Add a training face for a player.
     * $imagePath must be an absolute path to a local file.
     */
    public function addFaceExample(Player $player, string $imagePath): bool
    {
        if (empty($this->apiKey)) {
            Log::warning('CompreFace API Key missing. Skipping addFaceExample.');
            return false;
        }

        $subjectName = $this->getSubjectName($player);

        // Ensure subject exists first
        $this->createSubject($player);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->timeout(30)->retry(2, 1000, throw: false)->attach(
            'file', file_get_contents($imagePath), basename($imagePath)
        )->post($this->getBaseUrl().'/faces?subject='.urlencode($subjectName));

        if ($response->successful()) {
            return true;
        }

        Log::error('CompreFace Add Face Error: '.$response->body());

        return false;
    }

    /**
     * Add a training face from a Spatie Media object (S3 compatible).
     * Downloads the file from the media disk before sending to CompreFace.
     */
    public function addFaceExampleFromMedia(Player $player, Media $media): bool
    {
        $tempPath = $this->downloadMediaToTemp($media);
        if (! $tempPath) {
            Log::error('CompreFace: impossibile scaricare media per il training', [
                'player_id' => $player->id,
                'media_id' => $media->id,
            ]);
            return false;
        }

        try {
            return $this->addFaceExample($player, $tempPath);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Delete all examples for a specific subject. Useful for resetting/retraining.
     */
    public function deleteAllSubjectExamples(Player $player): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        $subjectName = $this->getSubjectName($player);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->timeout(15)->retry(2, 1000, throw: false)->delete($this->getBaseUrl().'/faces?subject='.urlencode($subjectName));

        return $response->successful();
    }

    /**
     * Recognize faces in an image.
     * Returns an array of detected subjects with confidence >= threshold.
     */
    public function recognizeFaces(string $imagePath, float $minConfidence = 0.85): array
    {
        if (empty($this->apiKey)) {
            Log::warning('CompreFace API Key missing. Skipping recognition.');

            return ['detected_players' => [], 'has_unrecognized_faces' => false];
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->timeout(30)->retry(2, 1000, throw: false)->attach(
            'file', file_get_contents($imagePath), basename($imagePath)
        )->post($this->getBaseUrl().'/recognize?limit=0&det_prob_threshold=0.8&prediction_count=1');

        if (! $response->successful()) {
            Log::error('CompreFace Recognize Error: '.$response->body());

            return ['detected_players' => [], 'has_unrecognized_faces' => false];
        }

        $result = $response->json();
        $detectedPlayers = [];
        $hasUnrecognizedFaces = false;

        if (isset($result['result'])) {
            foreach ($result['result'] as $face) {
                if (isset($face['subjects']) && count($face['subjects']) > 0) {
                    $topMatch = $face['subjects'][0];
                    if ($topMatch['similarity'] >= $minConfidence) {
                        $subject = $topMatch['subject'];
                        // Extract Player ID from subject name format: player_{id}
                        if (preg_match('/^player_(\d+)$/', $subject, $matches)) {
                            $detectedPlayers[] = [
                                'player_id' => (int) $matches[1],
                                'confidence' => $topMatch['similarity'] * 100, // convert to percentage 0-100
                            ];
                        }
                    } else {
                        $hasUnrecognizedFaces = true;
                    }
                } else {
                    $hasUnrecognizedFaces = true;
                }
            }
        }

        return [
            'detected_players' => $detectedPlayers,
            'has_unrecognized_faces' => $hasUnrecognizedFaces,
        ];
    }

    /**
     * Download a Spatie Media object to a temporary file.
     * Compatible with both local and S3 disks.
     */
    public function downloadMediaToTemp(Media $media): ?string
    {
        try {
            $disk = Storage::disk($media->disk);
            $relativePath = $media->getPathRelativeToRoot();

            if (! $disk->exists($relativePath)) {
                Log::warning("CompreFace: file not found on disk {$media->disk}: {$relativePath}");
                return null;
            }

            $tempPath = sys_get_temp_dir().'/'.uniqid('compreface_').'_'.$media->file_name;
            file_put_contents($tempPath, $disk->get($relativePath));

            return $tempPath;
        } catch (\Throwable $e) {
            Log::error('CompreFace: download media failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Helper to generate unique subject name per player.
     */
    protected function getSubjectName(Player $player): string
    {
        return 'player_'.$player->id;
    }
}

<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        return rtrim($this->host, '/') . '/api/v1/recognition';
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
        ])->post($this->getBaseUrl() . '/subjects', [
            'subject' => $subjectName
        ]);

        if ($response->successful() || $response->status() === 400) {
            // 400 usually means it already exists, we consider it success for our flow
            return true;
        }

        Log::error('CompreFace Create Subject Error: ' . $response->body());
        return false;
    }

    /**
     * Add a training face for a player.
     * $imagePath must be an absolute path to the local file, or a file stream.
     */
    public function addFaceExample(Player $player, string $imagePath): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        $subjectName = $this->getSubjectName($player);
        
        // Ensure subject exists first
        $this->createSubject($player);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->attach(
            'file', file_get_contents($imagePath), basename($imagePath)
        )->post($this->getBaseUrl() . '/faces?subject=' . urlencode($subjectName));

        if ($response->successful()) {
            return true;
        }

        Log::error('CompreFace Add Face Error: ' . $response->body());
        return false;
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
        ])->delete($this->getBaseUrl() . '/faces?subject=' . urlencode($subjectName));

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
            return [];
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->attach(
            'file', file_get_contents($imagePath), basename($imagePath)
        )->post($this->getBaseUrl() . '/recognize?limit=0&det_prob_threshold=0.8&prediction_count=1');

        if (!$response->successful()) {
            Log::error('CompreFace Recognize Error: ' . $response->body());
            return [];
        }

        $result = $response->json();
        $detectedPlayers = [];

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
                    }
                }
            }
        }

        return $detectedPlayers;
    }

    /**
     * Helper to generate unique subject name per player.
     */
    protected function getSubjectName(Player $player): string
    {
        return 'player_' . $player->id;
    }
}

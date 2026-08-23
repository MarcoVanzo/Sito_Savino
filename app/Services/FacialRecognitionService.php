<?php

namespace App\Services;

use App\Models\Player;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Model;
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
     * Create a subject for the person in CompreFace.
     */
    public function createSubject(Model $person): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        $subjectName = $this->getSubjectName($person);

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
     * Add a training face for a person (Player or StaffMember).
     * $imagePath must be an absolute path to a local file.
     * Returns an array: ['success' => bool, 'error' => ?string]
     */
    public function addFaceExample(Model $person, string $imagePath): array
    {
        if (empty($this->apiKey)) {
            Log::warning('CompreFace API Key missing. Skipping addFaceExample.');

            return ['success' => false, 'error' => 'API Key mancante'];
        }

        $subjectName = $this->getSubjectName($person);

        // Ensure subject exists first
        $this->createSubject($person);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->timeout(30)->retry(2, 1000, throw: false)->attach(
            'file', fopen($imagePath, 'r'), basename($imagePath)
        )->post($this->getBaseUrl().'/faces?subject='.urlencode($subjectName));

        if ($response->successful()) {
            return ['success' => true, 'error' => null];
        }

        $errorData = $response->json();
        $errorMessage = $errorData['message'] ?? $response->body();

        Log::error('CompreFace Add Face Error: '.$response->body());

        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Add a training face from a Spatie Media object (S3 compatible).
     * Downloads the file from the media disk before sending to CompreFace.
     */
    public function addFaceExampleFromMedia(Model $person, Media $media): array
    {
        $tempPath = $this->downloadMediaToTemp($media);
        if (! $tempPath) {
            Log::error('CompreFace: impossibile scaricare media per il training', [
                'person_type' => get_class($person),
                'person_id' => $person->id,
                'media_id' => $media->id,
            ]);

            return ['success' => false, 'error' => 'Impossibile scaricare file media'];
        }

        try {
            return $this->addFaceExample($person, $tempPath);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Delete all examples for a specific subject. Useful for resetting/retraining.
     */
    public function deleteAllSubjectExamples(Model $person): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        $subjectName = $this->getSubjectName($person);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->timeout(15)->retry(2, 1000, throw: false)->delete($this->getBaseUrl().'/faces?subject='.urlencode($subjectName));

        return $response->successful();
    }

    /**
     * Recognize faces in an image.
     * Returns an array of detected persons (Player or StaffMember) with confidence >= threshold.
     */
    public function recognizeFaces(string $imagePath, float $minConfidence = 0.985): array
    {
        if (empty($this->apiKey)) {
            Log::warning('CompreFace API Key missing. Skipping recognition.');

            throw new FacialRecognitionException('CompreFace API Key non configurata.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
        ])->timeout(30)->retry(2, 1000)->attach(
            'file', fopen($imagePath, 'r'), basename($imagePath)
        )->post($this->getBaseUrl().'/recognize?limit=0&det_prob_threshold=0.8&prediction_count=1');

        if (! $response->successful()) {
            $errorBody = $response->body();
            Log::error('CompreFace Recognize Error', [
                'status' => $response->status(),
                'body' => $errorBody,
            ]);

            throw new FacialRecognitionException("CompreFace API error (HTTP {$response->status()}): {$errorBody}");
        }

        $detectedPersons = [];
        $hasUnrecognizedFaces = false;

        foreach ($response->json()['result'] ?? [] as $face) {
            ['persona' => $persona, 'daRivedere' => $daRivedere] = $this->personaDelVolto($face, $minConfidence);

            if ($persona !== null) {
                $detectedPersons[] = $persona;
            }

            $hasUnrecognizedFaces = $hasUnrecognizedFaces || $daRivedere;
        }

        return [
            'detected_persons' => $detectedPersons,
            'has_unrecognized_faces' => $hasUnrecognizedFaces,
        ];
    }

    /**
     * La persona riconosciuta in un volto, e se quel volto lascia la foto da
     * rivedere in redazione.
     *
     * Un volto senza soggetti o sotto la soglia di somiglianza va rivisto. Un
     * soggetto riconosciuto ma non piu' in anagrafica non viene invece
     * segnalato: e' il comportamento storico, qui reso esplicito.
     *
     * @param  array<string, mixed>  $face
     * @return array{persona: array<string, mixed>|null, daRivedere: bool}
     */
    private function personaDelVolto(array $face, float $minConfidence): array
    {
        $topMatch = $face['subjects'][0] ?? null;

        if ($topMatch === null) {
            return ['persona' => null, 'daRivedere' => true];
        }

        if ($topMatch['similarity'] < $minConfidence) {
            Log::info("CompreFace Ignored: {$topMatch['subject']} (similarity: {$topMatch['similarity']} < {$minConfidence})");

            return ['persona' => null, 'daRivedere' => true];
        }

        $resolved = $this->resolveSubject($topMatch['subject']);

        if (! $resolved) {
            return ['persona' => null, 'daRivedere' => false];
        }

        Log::info("CompreFace Recognized: {$topMatch['subject']} (similarity: {$topMatch['similarity']})");

        return [
            'persona' => [
                'person_type' => $resolved['type'],
                'person_id' => $resolved['id'],
                'confidence' => $topMatch['similarity'] * 100,
            ],
            'daRivedere' => false,
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
     * Genera un nome soggetto univoco per CompreFace.
     * Player -> player_{id}, StaffMember -> staff_{id}
     */
    public function getSubjectName(Model $person): string
    {
        if ($person instanceof Player) {
            return 'player_'.$person->id;
        }

        if ($person instanceof StaffMember) {
            return 'staff_'.$person->id;
        }

        throw new \InvalidArgumentException('Unsupported model type: '.get_class($person));
    }

    /**
     * Risolve un subject name CompreFace in tipo e ID del modello.
     */
    public function resolveSubject(string $subjectName): ?array
    {
        if (preg_match('/^player_(\d+)$/', $subjectName, $matches)) {
            return ['type' => Player::class, 'id' => (int) $matches[1]];
        }

        if (preg_match('/^staff_(\d+)$/', $subjectName, $matches)) {
            return ['type' => StaffMember::class, 'id' => (int) $matches[1]];
        }

        return null;
    }
}

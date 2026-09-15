<?php

namespace App\Services;

use App\Models\Player;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FacialRecognitionService
{
    /**
     * Codice CompreFace per "No face is found in the given image".
     */
    private const ERRORE_NESSUN_VOLTO = 28;

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
     * Richiesta verso CompreFace.
     *
     * Se il server non risponde si rinuncia al primo tentativo, dopo cinque
     * secondi: tre tentativi da dieci superavano il tempo massimo di una
     * richiesta del pannello, e la redazione vedeva un 500 invece del messaggio
     * d'errore. Le risposte d'errore del server si ritentano come prima.
     */
    private function richiesta(int $timeout): PendingRequest
    {
        return Http::withHeaders(['x-api-key' => $this->apiKey])
            ->connectTimeout(5)
            ->timeout($timeout)
            ->retry(2, 1000, fn (\Throwable $e): bool => ! $e instanceof ConnectionException, throw: false);
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

        $response = $this->richiesta(15)->post($this->getBaseUrl().'/subjects', [
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

        if ($scarto = $this->motivoDiScartoComeEsempio($imagePath)) {
            return ['success' => false, 'error' => $scarto];
        }

        // Ensure subject exists first
        $this->createSubject($person);

        $response = $this->richiesta(30)->attach(
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
     * Perché una foto non va bene come esempio di volto, o null se va bene.
     *
     * CompreFace accetta qualunque immagine con un solo volto, anche una
     * miniatura. Il 15/09/2026 cinque foto in azione di un'atleta da 8-32 KB,
     * con volti alti fino a 44 px, hanno prodotto impronte che somigliavano a
     * chiunque: 123 foto dell'archivio taggate con lei al 99% di somiglianza,
     * tutte di una stagione in cui non era ancora in squadra. La misura del
     * volto si legge con lo stesso riconoscimento usato per la gallery, prima
     * di caricare: costa una chiamata in più su un'operazione rara.
     *
     * @throws ConnectionException se CompreFace non risponde
     */
    private function motivoDiScartoComeEsempio(string $imagePath): ?string
    {
        $response = $this->richiesta(30)->attach(
            'file', fopen($imagePath, 'r'), basename($imagePath)
        )->post($this->getBaseUrl().'/recognize?limit=0&det_prob_threshold=0.8&prediction_count=1');

        if ($response->status() === 400 && ($response->json()['code'] ?? null) === self::ERRORE_NESSUN_VOLTO) {
            return 'Nessun volto trovato nella foto.';
        }

        if (! $response->successful()) {
            // Sarà il caricamento vero a fallire con il messaggio del server.
            return null;
        }

        $volti = $response->json('result') ?? [];

        if (count($volti) !== 1) {
            return count($volti) === 0
                ? 'Nessun volto trovato nella foto.'
                : 'Trovati più volti nella foto (usa un primo piano).';
        }

        $box = $volti[0]['box'] ?? [];
        $altezza = (int) (($box['y_max'] ?? 0) - ($box['y_min'] ?? 0));
        $minimo = (int) config('services.compreface.min_face_px', 90);

        if ($altezza < $minimo) {
            return "Volto troppo piccolo ({$altezza} px, minimo {$minimo}): serve un primo piano ad alta risoluzione.";
        }

        return null;
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

        $response = $this->richiesta(15)->delete($this->getBaseUrl().'/faces?subject='.urlencode($subjectName));

        return $response->successful();
    }

    /**
     * Quanti esempi di volto conserva CompreFace per ogni soggetto
     * (`player_1` => 4, `staff_16` => 1, …).
     *
     * È l'unica fonte attendibile: il contatore `ai_face_examples` in
     * archivio si incrementa a ogni caricamento riuscito ma nessuno lo
     * riallinea quando il servizio viene azzerato o ricreato, e a settembre
     * 2026 diceva 3-7 esempi per atleta dove ce n'era uno solo.
     *
     * @return array<string, int>
     *
     * @throws ConnectionException se CompreFace non risponde
     * @throws FacialRecognitionException se CompreFace risponde con un errore
     */
    public function esempiPerSoggetto(): array
    {
        if (empty($this->apiKey)) {
            throw new FacialRecognitionException('CompreFace API Key non configurata.');
        }

        $conteggi = [];
        $pagina = 0;

        do {
            $response = $this->richiesta(15)->get($this->getBaseUrl().'/faces', ['page' => $pagina, 'size' => 1000]);

            if (! $response->successful()) {
                throw new FacialRecognitionException("CompreFace API error (HTTP {$response->status()}): {$response->body()}");
            }

            foreach ($response->json('faces') ?? [] as $volto) {
                $soggetto = (string) ($volto['subject'] ?? '');
                $conteggi[$soggetto] = ($conteggi[$soggetto] ?? 0) + 1;
            }

            $pagina++;
        } while ($pagina < (int) ($response->json('total_pages') ?? 1));

        return $conteggi;
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

        // `throw: false` come negli altri tre metodi del servizio: senza, al
        // terzo tentativo fallito Laravel lancia la propria RequestException e
        // il controllo qui sotto non viene mai raggiunto — cioe' proprio quando
        // CompreFace e' in errore non si scriveva il log con lo stato e il
        // corpo della risposta, e chi chiama riceveva un'eccezione diversa da
        // quella dichiarata.
        $response = $this->richiesta(30)->attach(
            'file', fopen($imagePath, 'r'), basename($imagePath)
        )->post($this->getBaseUrl().'/recognize?limit=0&det_prob_threshold=0.8&prediction_count=1');

        // "No face is found" (code 28) non e' un guasto: e' l'esito normale
        // per le foto senza volti (pubblico, palazzetto, dettagli di gioco) —
        // quasi un terzo della galleria. Trattarlo come errore faceva fallire
        // il job per tre tentativi e riempiva failed_jobs di falsi allarmi.
        if ($response->status() === 400 && ($response->json()['code'] ?? null) === self::ERRORE_NESSUN_VOLTO) {
            return [
                'detected_persons' => [],
                'has_unrecognized_faces' => false,
            ];
        }

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

<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chiamate alla Google Analytics Data API (v1beta).
 *
 * Tre soli verbi, tutti POST su
 *   https://analyticsdata.googleapis.com/v1beta/properties/{id}:runReport
 *   …:batchRunReports      (fino a 5 report in una chiamata)
 *   …:runRealtimeReport    (utenti negli ultimi 30 minuti)
 *
 * Le risposte escono già appiattite da `parseReport()`: ogni riga è una lista
 * di dimensioni (stringhe) e una di metriche (float), nell'ordine richiesto.
 * Così il servizio che le assembla non conosce la forma verbosa di Google e si
 * può testare con righe scritte a mano.
 */
class Ga4Client
{
    private const BASE_URL = 'https://analyticsdata.googleapis.com/v1beta/properties/';

    /** Limite imposto da Google su `batchRunReports`. */
    public const MAX_BATCH = 5;

    public function __construct(private readonly Ga4Credentials $credentials) {}

    /**
     * @param  array<string, mixed>  $request
     * @return array{rows: list<array{dims: list<string>, metrics: list<float>}>, row_count: int}
     *
     * @throws Ga4Exception
     */
    public function runReport(string $propertyId, array $request): array
    {
        return self::parseReport($this->call($propertyId, 'runReport', $request));
    }

    /**
     * Più report in una chiamata sola. I risultati tornano nell'ordine delle
     * richieste: il chiamante li associa per indice.
     *
     * @param  list<array<string, mixed>>  $requests
     * @return list<array{rows: list<array{dims: list<string>, metrics: list<float>}>, row_count: int}>
     *
     * @throws Ga4Exception
     */
    public function batchRunReports(string $propertyId, array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        if (count($requests) > self::MAX_BATCH) {
            throw new \InvalidArgumentException('batchRunReports accetta al massimo '.self::MAX_BATCH.' report');
        }

        $json = $this->call($propertyId, 'batchRunReports', ['requests' => $requests]);
        $reports = is_array($json['reports'] ?? null) ? $json['reports'] : [];

        $out = [];

        foreach (array_keys($requests) as $i) {
            $out[] = self::parseReport(is_array($reports[$i] ?? null) ? $reports[$i] : []);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array{rows: list<array{dims: list<string>, metrics: list<float>}>, row_count: int}
     *
     * @throws Ga4Exception
     */
    public function runRealtimeReport(string $propertyId, array $request): array
    {
        return self::parseReport($this->call($propertyId, 'runRealtimeReport', $request));
    }

    /**
     * Risposta GA4 → righe piatte. Un report senza `rows` non è un errore: è
     * una property senza traffico nel periodo.
     *
     * @param  array<string, mixed>  $json
     * @return array{rows: list<array{dims: list<string>, metrics: list<float>}>, row_count: int}
     */
    public static function parseReport(array $json): array
    {
        $rows = [];

        foreach ((array) ($json['rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $dims = [];

            foreach ((array) ($row['dimensionValues'] ?? []) as $dv) {
                $dims[] = (string) (is_array($dv) ? ($dv['value'] ?? '') : '');
            }

            $metrics = [];

            foreach ((array) ($row['metricValues'] ?? []) as $mv) {
                $metrics[] = (float) (is_array($mv) ? ($mv['value'] ?? 0) : 0);
            }

            $rows[] = ['dims' => $dims, 'metrics' => $metrics];
        }

        return ['rows' => $rows, 'row_count' => (int) ($json['rowCount'] ?? count($rows))];
    }

    /**
     * Errore HTTP di Google → causa parlante.
     *
     * Statico e pubblico apposta: è la parte che decide cosa leggerà l'utente,
     * e va verificata senza toccare la rete.
     */
    public static function reasonFor(int $status, string $body): string
    {
        $decoded = json_decode($body, true);
        $error = is_array($decoded) && is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $message = mb_strtolower((string) ($error['message'] ?? ''));
        $googleStatus = (string) ($error['status'] ?? '');

        if ($status === 429 || $googleStatus === 'RESOURCE_EXHAUSTED') {
            return 'quota';
        }

        if ($status === 403) {
            $disabled = str_contains($message, 'has not been used in project')
                || str_contains($message, 'is disabled');

            return $disabled ? 'api_disabled' : 'not_authorized';
        }

        if (in_array($status, [400, 404], true) || in_array($googleStatus, ['INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
            return 'bad_property';
        }

        if ($status === 401) {
            return 'auth_failed';
        }

        return 'unavailable';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws Ga4Exception
     */
    private function call(string $propertyId, string $method, array $body): array
    {
        if (! preg_match('/^\d{1,20}$/', $propertyId)) {
            throw new Ga4Exception('bad_property', "Property id non numerico: {$propertyId}");
        }

        $token = $this->credentials->accessToken();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.ga4.timeout', 30))
                ->post(self::BASE_URL.$propertyId.':'.$method, $body);
        } catch (\Throwable $e) {
            Log::warning('GA4: Data API non raggiungibile', ['method' => $method, 'error' => $e->getMessage()]);

            throw new Ga4Exception('unavailable', 'GA4 non raggiungibile: '.$e->getMessage());
        }

        if (! $response->successful()) {
            $reason = self::reasonFor($response->status(), $response->body());

            Log::warning('GA4: la Data API ha risposto con errore', [
                'method' => $method,
                'status' => $response->status(),
                'reason' => $reason,
                'property' => $propertyId,
            ]);

            throw new Ga4Exception($reason, "GA4 {$method} HTTP {$response->status()}", $response->status());
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new Ga4Exception('unavailable', 'Risposta GA4 non interpretabile come JSON');
        }

        return $json;
    }
}

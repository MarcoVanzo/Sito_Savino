<?php

namespace App\Services\GoogleTranslate;

use App\Services\Analytics\Ga4Credentials;
use App\Services\Analytics\Ga4Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Cloud Translation (API v3, modello neurale) per le notizie.
 *
 * Si autentica con il service account di GA4: il progetto Google è lo stesso
 * e il service account ha il ruolo «Utente API Cloud Translation». Scelto al
 * posto di DeepL perché il piano gratuito di DeepL è un credito una tantum,
 * qui invece 500.000 caratteri sono gratis ogni mese.
 */
class GoogleTranslateClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-translation';

    private const ENDPOINT = 'https://translation.googleapis.com/v3/projects/%s/locations/global:translateText';

    public function __construct(
        private readonly Ga4Credentials $credentials,
        private readonly int $timeout,
    ) {}

    /**
     * Null se il service account non c'è o non dice a quale progetto Google
     * appartiene: la traduzione automatica allora resta spenta.
     */
    public static function fromConfig(): ?self
    {
        $credentials = Ga4Credentials::fromConfig();

        if ($credentials === null || $credentials->projectId === '') {
            return null;
        }

        return new self($credentials, (int) config('services.google_translate.timeout', 60));
    }

    /**
     * Traduce i testi nell'ordine in cui arrivano, conservando le chiavi.
     *
     * @param  array<string, string>  $testi
     * @param  'text/plain'|'text/html'  $formato
     * @return array<string, string>
     *
     * @throws GoogleTranslateException
     */
    public function traduci(array $testi, string $da, string $a, string $formato = 'text/plain'): array
    {
        if ($testi === []) {
            return [];
        }

        try {
            $token = $this->credentials->accessToken(self::SCOPE);
        } catch (Ga4Exception $e) {
            throw new GoogleTranslateException('Token per la traduzione non ottenuto: '.$e->getMessage(), previous: $e);
        }

        try {
            $risposta = Http::withToken($token)
                ->timeout($this->timeout)
                ->post(sprintf(self::ENDPOINT, $this->credentials->projectId), [
                    'contents' => array_values($testi),
                    'mimeType' => $formato,
                    'sourceLanguageCode' => $da,
                    'targetLanguageCode' => $a,
                ]);
        } catch (ConnectionException $e) {
            throw new GoogleTranslateException('Google Translate non raggiungibile: '.$e->getMessage(), previous: $e);
        }

        if (! $risposta->successful()) {
            throw new GoogleTranslateException(sprintf(
                'Google Translate ha risposto %d: %s',
                $risposta->status(),
                mb_substr($risposta->body(), 0, 300),
            ));
        }

        /** @var list<array{translatedText?: string}> $traduzioni */
        $traduzioni = (array) $risposta->json('translations', []);

        if (count($traduzioni) !== count($testi)) {
            throw new GoogleTranslateException('Google Translate ha restituito un numero di testi diverso da quello inviato');
        }

        return array_combine(
            array_keys($testi),
            array_map(fn (array $traduzione): string => (string) ($traduzione['translatedText'] ?? ''), $traduzioni),
        );
    }
}

<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service account Google → access token per la GA4 Data API.
 *
 * Si è scelto il service account e non l'OAuth per utente perché quest'ultimo
 * richiederebbe la verifica dell'app da parte di Google per lo scope sensibile
 * `analytics.readonly`, e un token da rinnovare per ogni redattore. Con il
 * service account l'unico passo manuale è aggiungerne l'email come
 * Visualizzatore sulla property.
 *
 * Il JSON arriva da un file fuori dal repository oppure dalla variabile
 * d'ambiente (anche in base64, comodo per i secret di App Platform che non
 * amano gli a capo).
 */
class Ga4Credentials
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    /** Durata del JWT e, di fatto, del token che Google restituisce. */
    private const JWT_LIFETIME = 3600;

    /** Il token si ricicla poco meno della sua durata, per non usarlo scaduto. */
    private const CACHE_TTL = 3300;

    private function __construct(
        public readonly string $clientEmail,
        private readonly string $privateKey,
    ) {}

    public static function fromConfig(): ?self
    {
        $raw = self::rawServiceAccount();

        return $raw === null ? null : self::fromJson($raw);
    }

    public static function isConfigured(): bool
    {
        return self::fromConfig() !== null;
    }

    /**
     * Parsing puro: verificabile senza filesystem né rete.
     */
    public static function fromJson(string $json): ?self
    {
        $data = json_decode(self::decodeIfBase64($json), true);

        if (! is_array($data)) {
            Log::warning('GA4: il service account non è un JSON valido');

            return null;
        }

        $email = (string) ($data['client_email'] ?? '');
        $key = (string) ($data['private_key'] ?? '');

        if ($email === '' || $key === '') {
            Log::warning('GA4: service account senza client_email o private_key');

            return null;
        }

        return new self($email, $key);
    }

    /**
     * Access token OAuth2, tenuto in cache finché è valido.
     *
     * @throws Ga4Exception
     */
    public function accessToken(): string
    {
        $cached = Cache::get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $assertion = $this->signedAssertion();

        $response = Http::asForm()
            ->timeout((int) config('services.ga4.timeout', 30))
            ->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            Log::warning('GA4: scambio JWT → access token fallito', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);

            throw new Ga4Exception('auth_failed', 'Access token GA4 non ottenuto');
        }

        $token = (string) $response->json('access_token', '');

        if ($token === '') {
            throw new Ga4Exception('auth_failed', 'Google non ha restituito un access token');
        }

        Cache::put($this->cacheKey(), $token, self::CACHE_TTL);

        return $token;
    }

    /**
     * JWT RS256 firmato con la chiave privata del service account.
     *
     * @throws Ga4Exception
     */
    private function signedAssertion(): string
    {
        $now = time();
        $header = self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = self::base64Url((string) json_encode([
            'iss' => $this->clientEmail,
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + self::JWT_LIFETIME,
        ]));

        // Una chiave malformata (JSON troncato, a capo persi passando dai secret)
        // fa lanciare openssl_sign, non restituire false: senza questa rete la
        // pagina andrebbe in errore 500 invece di dire che il service account
        // non va bene.
        $key = openssl_pkey_get_private($this->privateKey);

        if ($key === false) {
            throw new Ga4Exception('auth_failed', 'Chiave privata del service account non leggibile');
        }

        $signature = '';

        try {
            $signed = openssl_sign($header.'.'.$claims, $signature, $key, OPENSSL_ALGO_SHA256);
        } catch (\Throwable $e) {
            throw new Ga4Exception('auth_failed', 'Firma del JWT fallita: '.$e->getMessage());
        }

        if (! $signed) {
            throw new Ga4Exception('auth_failed', 'Firma del JWT fallita: chiave privata non utilizzabile');
        }

        return $header.'.'.$claims.'.'.self::base64Url($signature);
    }

    /**
     * Chiave di cache legata all'identità: cambiando service account il token
     * vecchio non viene riusato.
     */
    private function cacheKey(): string
    {
        return 'ga4:token:'.sha1($this->clientEmail);
    }

    private static function rawServiceAccount(): ?string
    {
        $inline = (string) config('services.ga4.service_account_json', '');

        if (trim($inline) !== '') {
            return $inline;
        }

        $file = (string) config('services.ga4.service_account_file', '');

        if ($file !== '' && is_readable($file)) {
            $contents = file_get_contents($file);

            return $contents === false ? null : $contents;
        }

        return null;
    }

    /**
     * I secret di App Platform si passano volentieri in base64: un JSON con gli
     * a capo dentro una variabile d'ambiente è una fonte inesauribile di guai.
     */
    private static function decodeIfBase64(string $value): string
    {
        $trimmed = trim($value);

        if (str_starts_with($trimmed, '{')) {
            return $trimmed;
        }

        $decoded = base64_decode($trimmed, true);

        return is_string($decoded) && str_starts_with(ltrim($decoded), '{') ? $decoded : $trimmed;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trasporto verso la Graph API.
 *
 * Tutte le chiamate del modulo passano da qui: la rete sta in un punto solo e i
 * test la sostituiscono con `Http::fake()`. Il client non sa nulla di metriche
 * o di insight, si limita a tradurre gli errori di Meta in `MetaException`.
 */
class MetaClient
{
    private const BASE_URL = 'https://graph.facebook.com/';

    /**
     * GET su un percorso della Graph API.
     *
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     *
     * @throws MetaException
     */
    public function get(string $path, array $params): array
    {
        $url = self::BASE_URL.$this->version().'/'.ltrim($path, '/');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.meta.timeout', 30))
                ->get($url, $params);
        } catch (\Throwable $e) {
            Log::warning('Meta: Graph API non raggiungibile', ['path' => $path, 'error' => $e->getMessage()]);

            throw new MetaException('unavailable', 'Graph API non raggiungibile: '.$e->getMessage());
        }

        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if (! $response->successful()) {
            $error = is_array($json['error'] ?? null) ? $json['error'] : [];
            $exception = MetaException::fromGraphError($error, $response->status());

            Log::warning('Meta: la Graph API ha risposto con errore', [
                'path' => $path,
                'status' => $response->status(),
                'reason' => $exception->reason,
                'code' => $exception->graphCode,
                'message' => mb_substr($exception->getMessage(), 0, 300),
            ]);

            throw $exception;
        }

        return $json;
    }

    /**
     * Codice OAuth → token utente di breve durata.
     *
     * @return array{access_token: string, expires_in: int}
     *
     * @throws MetaException
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $json = $this->get('oauth/access_token', [
            'client_id' => (string) config('services.meta.app_id'),
            'client_secret' => (string) config('services.meta.app_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        return [
            'access_token' => (string) ($json['access_token'] ?? ''),
            'expires_in' => (int) ($json['expires_in'] ?? 0),
        ];
    }

    /**
     * Token utente di breve durata → token long-lived (circa 60 giorni).
     *
     * @return array{access_token: string, expires_in: int}
     *
     * @throws MetaException
     */
    public function longLivedToken(string $shortLivedToken): array
    {
        $json = $this->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => (string) config('services.meta.app_id'),
            'client_secret' => (string) config('services.meta.app_secret'),
            'fb_exchange_token' => $shortLivedToken,
        ]);

        return [
            'access_token' => (string) ($json['access_token'] ?? ''),
            'expires_in' => (int) ($json['expires_in'] ?? 0),
        ];
    }

    /**
     * Pagine amministrate dall'utente, con il loro token e l'eventuale profilo
     * Instagram business collegato.
     *
     * I token di Pagina derivati da un token utente long-lived non scadono: è il
     * motivo per cui si passa sempre da `longLivedToken()` prima di leggere qui.
     *
     * @return list<array<string, mixed>>
     *
     * @throws MetaException
     */
    public function pages(string $userToken): array
    {
        $json = $this->get('me/accounts', [
            'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            'limit' => 100,
            'access_token' => $userToken,
        ]);

        $pages = [];

        foreach ((array) ($json['data'] ?? []) as $page) {
            if (is_array($page) && filled($page['id'] ?? null)) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    public function version(): string
    {
        return (string) config('services.meta.graph_version', 'v24.0');
    }
}

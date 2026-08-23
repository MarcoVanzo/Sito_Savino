<?php

namespace App\Services\Lvf;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Accesso HTTP al sito della Lega Volley Femminile.
 *
 * Il sito è dietro Cloudflare e non espone API: si scaricano le pagine
 * pubbliche e le si parsa. Ci si identifica con uno user agent riconoscibile
 * e si tiene un solo tentativo di retry, per non pesare sull'origine.
 */
class LvfClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly string $userAgent,
        private readonly string $statsBaseUrl = '',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('services.lvf.base_url'), '/'),
            (int) config('services.lvf.timeout', 30),
            (string) config('services.lvf.user_agent'),
            rtrim((string) config('services.lvf.stats_base_url'), '/'),
        );
    }

    /**
     * Calendario completo di una stagione: contiene anche le gare non giocate,
     * con giornata, fase (andata/ritorno) e impianto.
     */
    public function calendar(int $seasonYear): string
    {
        return $this->get('/calendario/', ['stagione' => $seasonYear]);
    }

    /**
     * Pagina risultati: stesse gare del calendario ma con i set vinti.
     */
    public function results(int $seasonYear): string
    {
        return $this->get('/risultati/', ['stagione' => $seasonYear]);
    }

    public function standings(int $seasonYear): string
    {
        return $this->get('/classifica/', ['stagione' => $seasonYear]);
    }

    /**
     * Tabellino di una gara: statistiche individuali di entrambe le squadre,
     * parziali dei set, spettatori e arbitri.
     *
     * Vive su un host diverso (`ww5`) ed è la pagina che il Match Center carica
     * dentro un iframe; `IdGara` coincide con l'identificativo del Match Center.
     */
    public function boxScore(int $lvfMatchId): string
    {
        $html = $this->get('/TabellinoGara_i.asp', ['IdGara' => $lvfMatchId], $this->statsBaseUrl);

        return $this->toUtf8($html);
    }

    /**
     * La pagina del tabellino è un vecchio ASP servito in Windows-1252: senza
     * conversione i nomi con accenti arrivano corrotti.
     */
    private function toUtf8(string $html): string
    {
        if (mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        return mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
    }

    /**
     * @param  array<string, int|string>  $query
     *
     * @throws RuntimeException se la pagina non è raggiungibile o risponde con errore
     */
    private function get(string $path, array $query = [], ?string $baseUrl = null): string
    {
        $url = ($baseUrl !== null && $baseUrl !== '' ? $baseUrl : $this->baseUrl).$path;

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'it-IT,it;q=0.9',
            ])
                ->timeout($this->timeout)
                ->retry(2, 2000, throw: false)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw new LvfException("Connessione a {$url} fallita: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new LvfException("{$url} ha risposto con HTTP {$response->status()}.");
        }

        $body = $response->body();

        if (trim($body) === '') {
            throw new LvfException("{$url} ha restituito una risposta vuota.");
        }

        return $body;
    }
}

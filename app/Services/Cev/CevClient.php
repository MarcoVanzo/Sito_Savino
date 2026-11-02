<?php

namespace App\Services\Cev;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Accesso HTTP al vecchio portale competizioni della CEV.
 *
 * Il sito nuovo (championsleague.cev.eu) disegna calendario e classifiche in
 * JavaScript e non espone un'API; il vecchio portale ASP.NET, che la CEV tiene
 * aggiornato con le stesse gare, pubblica invece pagine HTML statiche. Come
 * per la Lega ci si identifica con uno user agent riconoscibile e si fa una
 * pausa fra una pagina e l'altra: il portale è lento e un giro sono sei pagine.
 */
class CevClient
{
    /** Quante volte si chiede una pagina che torna senza il suo marcatore. */
    private const TENTATIVI_SULLA_PAGINA = 3;

    private const ATTESA_FRA_I_TENTATIVI_MS = 2000;

    private bool $primaRichiesta = true;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly string $userAgent,
        private readonly int $pausaMs = 0,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('services.cev.base_url'), '/'),
            (int) config('services.cev.timeout', 30),
            (string) config('services.cev.user_agent'),
            (int) config('services.cev.pausa_ms', 0),
        );
    }

    /**
     * Pagina principale della competizione: da qui si leggono le fasi
     * (turni preliminari, gironi, play off, quarti, Final Four).
     */
    public function competition(int $competitionId): string
    {
        return $this->get('/Competition.aspx', ['ID' => $competitionId], 'PID=');
    }

    /**
     * Tutte le gare di una fase, con data, ora locale, impianto e set.
     */
    public function matches(int $competitionId, int $phaseId): string
    {
        return $this->get('/CompetitionView.aspx', ['ID' => $competitionId, 'PID' => $phaseId], 'RadTabStripPhase');
    }

    /**
     * Classifiche di tutti i gironi di una fase.
     */
    public function standings(int $competitionId, int $phaseId): string
    {
        return $this->get('/CompetitionStandings.aspx', ['ID' => $competitionId, 'PID' => $phaseId], 'RadTabStripPhase');
    }

    /**
     * @param  array<string, int|string>  $query
     * @param  string  $marcatore  testo che una pagina valida contiene sempre
     */
    private function get(string $path, array $query, string $marcatore): string
    {
        if (! $this->primaRichiesta && $this->pausaMs > 0) {
            usleep($this->pausaMs * 1000);
        }

        $this->primaRichiesta = false;
        $url = $this->baseUrl.$path;

        for ($tentativo = 1; ; $tentativo++) {
            $body = $this->scarica($url, $query);

            // Il portale risponde 200 anche con la sua pagina d'errore: senza il
            // marcatore atteso non è la pagina che si è chiesta. Di solito è un
            // intoppo passeggero (il 02/11/2026 due giri falliti su sei): si
            // riprova prima di fallire.
            if (trim($body) !== '' && str_contains($body, $marcatore)) {
                return $body;
            }

            if ($tentativo >= self::TENTATIVI_SULLA_PAGINA) {
                throw new CevException("{$url} non ha restituito una pagina di competizione.");
            }

            Sleep::for(self::ATTESA_FRA_I_TENTATIVI_MS)->milliseconds();
        }
    }

    /**
     * @param  array<string, int|string>  $query
     */
    private function scarica(string $url, array $query): string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en-GB,en;q=0.9',
            ])
                ->timeout($this->timeout)
                ->retry(2, self::ATTESA_FRA_I_TENTATIVI_MS, throw: false)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw new CevException("Connessione a {$url} fallita: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new CevException("{$url} ha risposto con HTTP {$response->status()}.");
        }

        return $response->body();
    }
}

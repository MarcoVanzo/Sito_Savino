<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsSite;
use App\Models\WebAnalyticsDaily;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * I dati della pagina "Analytics Sito".
 *
 * Un'apertura della pagina = due chiamate a GA4 (il batch ne accetta 5 report
 * per volta, i report sono otto) più una per il tempo reale. Il risultato resta
 * in cache un'ora: la pagina la aprono in più persone e i numeri di GA4 non
 * cambiano abbastanza da giustificare una chiamata a testa.
 *
 * Verso l'esterno il servizio non lancia eccezioni: la pagina deve restare in
 * piedi anche quando Google non risponde. Un guasto diventa `error`, e se in
 * archivio c'è già la serie giornaliera la si mostra marcata come `degraded`
 * invece di lasciare la pagina bianca.
 */
class WebAnalyticsService
{
    public const ALLOWED_DAYS = [7, 28, 90];

    private const CACHE_OVERVIEW_TTL = 3600;

    private const CACHE_REALTIME_TTL = 60;

    /** Giorni dopo i quali GA4 non rielabora più il dato. */
    private const FINAL_AFTER_DAYS = 2;

    /**
     * Memoria per richiesta: la pagina e i suoi widget chiedono gli stessi dati
     * nello stesso ciclo di vita. Senza, ogni widget ripasserebbe dalla cache
     * (serializzazione compresa) per lo stesso identico payload.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $memo = [];

    private ?Ga4Client $client = null;

    public function __construct(?Ga4Client $client = null)
    {
        $this->client = $client;
    }

    public function isConfigured(): bool
    {
        return Ga4Credentials::isConfigured();
    }

    public function serviceAccountEmail(): ?string
    {
        return Ga4Credentials::fromConfig()?->clientEmail;
    }

    /** @return Collection<int, AnalyticsSite> */
    public function sites()
    {
        return AnalyticsSite::query()->ordered()->get();
    }

    /**
     * Dati completi di un sito per il periodo richiesto.
     *
     * @return array<string, mixed>
     */
    public function overview(AnalyticsSite $site, int $days): array
    {
        $days = in_array($days, self::ALLOWED_DAYS, true) ? $days : 28;
        $memoKey = $site->id.':'.$days;

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        if (! $this->isConfigured()) {
            return $this->memo[$memoKey] = $this->failure('not_configured', $days);
        }

        $cacheKey = "ga4:overview:{$site->id}:{$site->property_id}:{$days}";

        try {
            $data = Cache::remember(
                $cacheKey,
                self::CACHE_OVERVIEW_TTL,
                fn (): array => $this->fetchOverview($site, $days),
            );
        } catch (Ga4Exception $e) {
            $data = $this->degradedFromHistory($site, $days, $e);
        }

        $data['realtime'] = $this->realtime($site);

        return $this->memo[$memoKey] = $data;
    }

    /**
     * Prova d'accesso a una property, per la schermata di configurazione.
     *
     * @return array{ok: bool, reason?: string, message?: string, active_users_7d?: int}
     */
    public function verify(string $propertyId): array
    {
        if (! $this->isConfigured()) {
            $e = new Ga4Exception('not_configured', 'Service account assente');

            return ['ok' => false, 'reason' => $e->reason, 'message' => $e->userMessage()];
        }

        try {
            $report = $this->client()->runReport($propertyId, [
                'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']],
                'metrics' => [['name' => 'activeUsers']],
            ]);
        } catch (Ga4Exception $e) {
            return ['ok' => false, 'reason' => $e->reason, 'message' => $e->userMessage()];
        }

        return ['ok' => true, 'active_users_7d' => (int) ($report['rows'][0]['metrics'][0] ?? 0)];
    }

    // ─── Interni ─────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function fetchOverview(AnalyticsSite $site, int $days): array
    {
        $requests = Ga4ReportAssembler::buildRequests($days);
        $client = $this->client();

        // Il batch di Google ne accetta cinque per volta: due chiamate, non otto.
        $reports = array_merge(
            $client->batchRunReports($site->property_id, array_slice($requests, 0, Ga4Client::MAX_BATCH)),
            $client->batchRunReports($site->property_id, array_slice($requests, Ga4Client::MAX_BATCH)),
        );

        $data = Ga4ReportAssembler::assemble($reports, $days);
        $data['site'] = ['id' => $site->id, 'name' => $site->name, 'property_id' => $site->property_id];
        $data['fetched_at'] = now()->toIso8601String();

        $this->persistDaily($site, $data['daily']);

        return $data;
    }

    /** @return array{active_users: int|null, error?: string} */
    private function realtime(AnalyticsSite $site): array
    {
        try {
            return Cache::remember(
                "ga4:realtime:{$site->property_id}",
                self::CACHE_REALTIME_TTL,
                function () use ($site): array {
                    $report = $this->client()->runRealtimeReport($site->property_id, [
                        'metrics' => [['name' => 'activeUsers']],
                    ]);

                    return ['active_users' => (int) ($report['rows'][0]['metrics'][0] ?? 0)];
                },
            );
        } catch (Ga4Exception $e) {
            // Il tempo reale è un di più: senza, la pagina resta intera.
            return ['active_users' => null, 'error' => $e->reason];
        }
    }

    /**
     * Guasto di GA4: se la serie storica c'è la si mostra dicendo che è vecchia,
     * altrimenti si restituisce l'errore.
     *
     * @return array<string, mixed>
     */
    private function degradedFromHistory(AnalyticsSite $site, int $days, Ga4Exception $e): array
    {
        $recoverable = in_array($e->reason, ['quota', 'unavailable', 'auth_failed'], true);
        $history = $recoverable ? $this->fromHistory($site, $days) : null;

        if ($history === null) {
            return $this->failure($e->reason, $days, $e->userMessage());
        }

        $history['degraded'] = ['reason' => $e->reason, 'message' => $e->userMessage()];

        return $history;
    }

    /**
     * Payload "vuoto ma valido": la pagina sa disegnarlo senza casi speciali.
     *
     * @return array<string, mixed>
     */
    private function failure(string $reason, int $days, ?string $message = null): array
    {
        $empty = Ga4ReportAssembler::assemble([], $days);
        $empty['ok'] = false;
        $empty['error'] = [
            'reason' => $reason,
            'message' => $message ?? (new Ga4Exception($reason, ''))->userMessage(),
        ];
        $empty['realtime'] = ['active_users' => null];

        return $empty;
    }

    /**
     * La serie giornaliera si conserva a ogni lettura.
     *
     * @param  list<array<string, mixed>>  $daily
     */
    private function persistDaily(AnalyticsSite $site, array $daily): void
    {
        if ($daily === []) {
            return;
        }

        $finalBefore = Ga4ReportAssembler::today()->modify('-'.self::FINAL_AFTER_DAYS.' days')->format('Y-m-d');

        try {
            foreach ($daily as $row) {
                // I giorni riempiti a zero da assemble() (non tornati da GA4) non
                // devono sovrascrivere un valore già salvato: si scrivono solo i
                // giorni per cui Google ha riportato qualcosa.
                if (Ga4ReportAssembler::isEmptyDay($row)) {
                    continue;
                }

                WebAnalyticsDaily::updateOrCreate(
                    ['analytics_site_id' => $site->id, 'day' => $row['day']],
                    [
                        'property_id' => $site->property_id,
                        'active_users' => $row['active_users'],
                        'new_users' => $row['new_users'],
                        'sessions' => $row['sessions'],
                        'page_views' => $row['page_views'],
                        'engaged_sessions' => $row['engaged_sessions'],
                        'engagement_seconds' => $row['engagement_seconds'],
                        'is_final' => $row['day'] <= $finalBefore,
                    ],
                );
            }
        } catch (\Throwable $e) {
            // Lo storico è un di più: un errore qui non deve togliere la pagina.
            Log::warning('GA4: salvataggio della serie giornaliera fallito', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Ricostruisce il minimo indispensabile dalla serie salvata: KPI e grafico.
     * Le ripartizioni (pagine, canali, città) non sono in archivio e restano vuote.
     *
     * @return array<string, mixed>|null
     */
    private function fromHistory(AnalyticsSite $site, int $days): ?array
    {
        $start = Ga4ReportAssembler::today()->modify('-'.($days - 1).' days')->format('Y-m-d');

        $saved = WebAnalyticsDaily::query()
            ->where('analytics_site_id', $site->id)
            ->where('day', '>=', $start)
            ->orderBy('day')
            ->get()
            ->keyBy(fn (WebAnalyticsDaily $row): string => $row->day->format('Y-m-d'));

        if ($saved->isEmpty()) {
            return null;
        }

        $data = Ga4ReportAssembler::assemble([], $days);
        $totals = Ga4ReportAssembler::emptyTotals();
        $daily = [];

        foreach ($data['daily'] as $row) {
            $stored = $saved->get($row['day']);

            if ($stored !== null) {
                $row = [
                    'day' => $row['day'],
                    'active_users' => (int) $stored->active_users,
                    'new_users' => (int) $stored->new_users,
                    'sessions' => (int) $stored->sessions,
                    'page_views' => (int) $stored->page_views,
                    'engaged_sessions' => (int) $stored->engaged_sessions,
                    'engagement_seconds' => (int) $stored->engagement_seconds,
                ];

                foreach (['active_users', 'new_users', 'sessions', 'page_views', 'engaged_sessions', 'engagement_seconds'] as $key) {
                    $totals[$key] += $row[$key];
                }
            }

            $daily[] = $row;
        }

        $totals['engagement_rate'] = $totals['sessions'] > 0
            ? round($totals['engaged_sessions'] / $totals['sessions'] * 100, 1)
            : 0.0;
        $totals['avg_session_duration'] = $totals['sessions'] > 0
            ? (int) round($totals['engagement_seconds'] / $totals['sessions'])
            : 0;

        $data['daily'] = $daily;
        $data['totals'] = $totals;
        $data['deltas'] = array_map(static fn (): ?float => null, $totals);
        $data['site'] = ['id' => $site->id, 'name' => $site->name, 'property_id' => $site->property_id];

        return $data;
    }

    private function client(): Ga4Client
    {
        if ($this->client === null) {
            $credentials = Ga4Credentials::fromConfig();

            if ($credentials === null) {
                throw new Ga4Exception('not_configured', 'Service account GA4 assente');
            }

            $this->client = new Ga4Client($credentials);
        }

        return $this->client;
    }
}

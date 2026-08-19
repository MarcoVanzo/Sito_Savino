<?php

namespace App\Services\Analytics;

/**
 * Le richieste da mandare a GA4 e la traduzione delle risposte nel payload che
 * la pagina disegna.
 *
 * Sta a parte dal servizio perché non tocca né rete né database: è la parte del
 * modulo che si può verificare per intero con righe scritte a mano, ed è anche
 * quella che si rompe per prima se Google cambia nomi di metriche o dimensioni.
 */
class Ga4ReportAssembler
{
    /** Righe massime nelle classifiche (pagine, città). */
    private const TOP_ROWS = 10;

    /** Metriche dei totali, nell'ordine in cui vengono chieste e riconsegnate. */
    private const TOTAL_METRICS = [
        'activeUsers', 'newUsers', 'sessions', 'screenPageViews',
        'averageSessionDuration', 'engagementRate', 'bounceRate', 'eventCount',
        'engagedSessions', 'userEngagementDuration',
    ];

    /**
     * Gli otto report che compongono la pagina, nell'ordine in cui `assemble()`
     * si aspetta di trovarli.
     *
     * @return list<array<string, mixed>>
     */
    public static function buildRequests(int $days): array
    {
        $current = ['startDate' => ($days - 1).'daysAgo', 'endDate' => 'today'];
        $previous = ['startDate' => (2 * $days - 1).'daysAgo', 'endDate' => $days.'daysAgo'];

        $names = static fn (string ...$list): array => array_map(
            static fn (string $n): array => ['name' => $n],
            $list,
        );
        $desc = static fn (string $metric): array => [
            ['metric' => ['metricName' => $metric], 'desc' => true],
        ];

        return [
            // 0 — totali del periodo corrente e del precedente, per i delta
            ['dateRanges' => [$current, $previous], 'metrics' => $names(...self::TOTAL_METRICS)],
            // 1 — serie giornaliera
            [
                'dateRanges' => [$current],
                'dimensions' => $names('date'),
                'metrics' => $names('activeUsers', 'newUsers', 'sessions', 'screenPageViews', 'engagedSessions', 'userEngagementDuration'),
                'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
                'limit' => 400,
            ],
            // 2 — pagine più viste: il "pagina per pagina"
            [
                'dateRanges' => [$current],
                'dimensions' => $names('pagePath', 'pageTitle'),
                'metrics' => $names('screenPageViews', 'activeUsers', 'averageSessionDuration'),
                'orderBys' => $desc('screenPageViews'),
                'limit' => self::TOP_ROWS,
            ],
            // 3 — canali
            [
                'dateRanges' => [$current],
                'dimensions' => $names('sessionDefaultChannelGroup'),
                'metrics' => $names('sessions', 'activeUsers'),
                'orderBys' => $desc('sessions'),
                'limit' => 8,
            ],
            // 4 — sorgenti
            [
                'dateRanges' => [$current],
                'dimensions' => $names('sessionSource'),
                'metrics' => $names('sessions', 'activeUsers'),
                'orderBys' => $desc('sessions'),
                'limit' => 8,
            ],
            // 5 — dispositivi
            [
                'dateRanges' => [$current],
                'dimensions' => $names('deviceCategory'),
                'metrics' => $names('activeUsers', 'sessions'),
                'orderBys' => $desc('activeUsers'),
                'limit' => 5,
            ],
            // 6 — città
            [
                'dateRanges' => [$current],
                'dimensions' => $names('city', 'country'),
                'metrics' => $names('activeUsers'),
                'orderBys' => $desc('activeUsers'),
                'limit' => self::TOP_ROWS,
            ],
            // 7 — pagine di ingresso
            [
                'dateRanges' => [$current],
                'dimensions' => $names('landingPage'),
                'metrics' => $names('sessions', 'engagementRate'),
                'orderBys' => $desc('sessions'),
                'limit' => 8,
            ],
        ];
    }

    /**
     * Report GA4 (nell'ordine di `buildRequests`) → payload della pagina.
     * Funzione pura: niente rete, niente database.
     *
     * @param  list<array{rows: list<array{dims: list<string>, metrics: list<float>}>}>  $reports
     * @return array<string, mixed>
     */
    public static function assemble(array $reports, int $days): array
    {
        $rows = static fn (int $i): array => $reports[$i]['rows'] ?? [];

        // 0 — i totali arrivano come una riga per intervallo: la dimensione
        // implicita vale "date_range_0" per il corrente e "_1" per il precedente.
        $totals = self::emptyTotals();
        $previous = self::emptyTotals();

        foreach ($rows(0) as $row) {
            $bucket = self::totalsFromMetrics($row['metrics']);

            if (($row['dims'][0] ?? 'date_range_0') === 'date_range_1') {
                $previous = $bucket;
            } else {
                $totals = $bucket;
            }
        }

        $deltas = [];

        foreach ($totals as $key => $value) {
            $deltas[$key] = self::delta((float) $value, (float) ($previous[$key] ?? 0));
        }

        return [
            'ok' => true,
            'period' => [
                'days' => $days,
                'start' => self::today()->modify('-'.($days - 1).' days')->format('Y-m-d'),
                'end' => self::today()->format('Y-m-d'),
            ],
            'totals' => $totals,
            'previous' => $previous,
            'deltas' => $deltas,
            'daily' => self::assembleDaily($rows(1), $days),
            'pages' => self::assemblePages($rows(2)),
            'channels' => self::named($rows(3), 'name', ['sessions', 'users']),
            'sources' => self::named($rows(4), 'name', ['sessions', 'users']),
            'devices' => self::named($rows(5), 'name', ['users', 'sessions']),
            'cities' => self::assembleCities($rows(6)),
            'landing' => self::named($rows(7), 'page', ['sessions', 'engagement_rate']),
            'degraded' => null,
        ];
    }

    /**
     * "Oggi" nel fuso della property.
     *
     * PHP gira in UTC, ma `today` e `NdaysAgo` nelle richieste li risolve Google
     * nel fuso della property: calcolare le chiavi della serie in UTC farebbe
     * risultare vuoto l'ultimo giorno per due ore ogni sera.
     */
    public static function today(): \DateTimeImmutable
    {
        $zone = (string) config('services.ga4.timezone', 'Europe/Rome');

        return new \DateTimeImmutable('today', new \DateTimeZone($zone));
    }

    /** Variazione percentuale a un decimale; null quando il periodo precedente è a zero. */
    public static function delta(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * @param  list<array{dims: list<string>, metrics: list<float>}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function assembleDaily(array $rows, int $days): array
    {
        $byDay = [];

        foreach ($rows as $row) {
            $raw = $row['dims'][0] ?? '';

            if (! preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m)) {
                continue;
            }

            $byDay["{$m[1]}-{$m[2]}-{$m[3]}"] = [
                'active_users' => (int) ($row['metrics'][0] ?? 0),
                'new_users' => (int) ($row['metrics'][1] ?? 0),
                'sessions' => (int) ($row['metrics'][2] ?? 0),
                'page_views' => (int) ($row['metrics'][3] ?? 0),
                'engaged_sessions' => (int) ($row['metrics'][4] ?? 0),
                'engagement_seconds' => (int) round((float) ($row['metrics'][5] ?? 0)),
            ];
        }

        // La serie si riempie giorno per giorno anche dove GA4 non ha righe:
        // un grafico con i buchi si legge peggio di uno con gli zeri.
        $daily = [];
        $today = self::today();

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->modify("-{$i} days")->format('Y-m-d');
            $daily[] = ['day' => $day] + ($byDay[$day] ?? [
                'active_users' => 0, 'new_users' => 0, 'sessions' => 0,
                'page_views' => 0, 'engaged_sessions' => 0, 'engagement_seconds' => 0,
            ]);
        }

        return $daily;
    }

    /**
     * @param  list<array{dims: list<string>, metrics: list<float>}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function assemblePages(array $rows): array
    {
        $pages = [];

        foreach ($rows as $row) {
            $pages[] = [
                'path' => $row['dims'][0] ?? '',
                'title' => $row['dims'][1] ?? '',
                'views' => (int) ($row['metrics'][0] ?? 0),
                'users' => (int) ($row['metrics'][1] ?? 0),
                'avg_duration' => (int) round((float) ($row['metrics'][2] ?? 0)),
            ];
        }

        return $pages;
    }

    /**
     * @param  list<array{dims: list<string>, metrics: list<float>}>  $rows
     * @return list<array<string, mixed>>
     */
    private static function assembleCities(array $rows): array
    {
        $cities = [];

        foreach ($rows as $row) {
            $cities[] = [
                'city' => ($row['dims'][0] ?? '') !== '' ? $row['dims'][0] : '(sconosciuta)',
                'country' => $row['dims'][1] ?? '',
                'users' => (int) ($row['metrics'][0] ?? 0),
            ];
        }

        return $cities;
    }

    /**
     * Righe a una dimensione → lista di voci con metriche nominate.
     *
     * @param  list<array{dims: list<string>, metrics: list<float>}>  $rows
     * @param  list<string>  $metricNames
     * @return list<array<string, mixed>>
     */
    private static function named(array $rows, string $key, array $metricNames): array
    {
        $out = [];

        foreach ($rows as $row) {
            $label = ($row['dims'][0] ?? '') !== '' ? $row['dims'][0] : '(non impostato)';
            $item = [$key => $label];

            foreach ($metricNames as $i => $name) {
                $value = (float) ($row['metrics'][$i] ?? 0);
                // GA4 dà i tassi come frazione: la UI li mostra in percentuale.
                $item[$name] = $name === 'engagement_rate' ? round($value * 100, 1) : (int) $value;
            }

            $out[] = $item;
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    public static function isEmptyDay(array $row): bool
    {
        foreach (['active_users', 'new_users', 'sessions', 'page_views', 'engaged_sessions', 'engagement_seconds'] as $key) {
            if ((int) ($row[$key] ?? 0) !== 0) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, int|float> */
    public static function emptyTotals(): array
    {
        return [
            'active_users' => 0, 'new_users' => 0, 'sessions' => 0, 'page_views' => 0,
            'avg_session_duration' => 0, 'engagement_rate' => 0.0, 'bounce_rate' => 0.0,
            'events' => 0, 'engaged_sessions' => 0, 'engagement_seconds' => 0,
        ];
    }

    /**
     * @param  list<float>  $m  nell'ordine di TOTAL_METRICS
     * @return array<string, int|float>
     */
    private static function totalsFromMetrics(array $m): array
    {
        return [
            'active_users' => (int) ($m[0] ?? 0),
            'new_users' => (int) ($m[1] ?? 0),
            'sessions' => (int) ($m[2] ?? 0),
            'page_views' => (int) ($m[3] ?? 0),
            'avg_session_duration' => (int) round((float) ($m[4] ?? 0)),
            'engagement_rate' => round(((float) ($m[5] ?? 0)) * 100, 1),
            'bounce_rate' => round(((float) ($m[6] ?? 0)) * 100, 1),
            'events' => (int) ($m[7] ?? 0),
            'engaged_sessions' => (int) ($m[8] ?? 0),
            'engagement_seconds' => (int) round((float) ($m[9] ?? 0)),
        ];
    }
}

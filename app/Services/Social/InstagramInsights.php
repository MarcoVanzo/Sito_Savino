<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Le letture Instagram della Graph API.
 *
 * Due avvertenze che spiegano la forma di questa classe:
 *
 * 1. Meta restituisce le metriche di account in due modi incompatibili.
 *    `time_series` vale solo per `reach` e `follower_count` e dà un valore al
 *    giorno; `total_value` vale per tutto il resto e dà UN numero per l'intero
 *    intervallo. Qui ci sono entrambe le letture, e la serie giorno per giorno
 *    la costruisce {@see InstagramDailySync} interrogando un giorno alla volta.
 *
 * 2. Non tutte le metriche esistono per tutti gli account. Meta ne ha ritirate
 *    a ondate e alcune richiedono soglie di follower. Ogni blocco è quindi
 *    indipendente: se Meta ne rifiuta uno quel blocco vale null e gli altri
 *    restano, invece di far cadere l'intera pagina.
 */
class InstagramInsights
{
    /** Metriche `total_value` senza breakdown: valide sia per giorno sia per periodo. */
    public const TOTAL_METRICS = [
        'views', 'likes', 'comments', 'shares', 'saves', 'reposts', 'replies',
        'total_interactions', 'accounts_engaged', 'profile_links_taps',
    ];

    /** Ripiego quando l'elenco completo viene rifiutato dall'account. */
    private const TOTAL_METRICS_MINIMAL = [
        'views', 'likes', 'comments', 'shares', 'saves', 'total_interactions',
    ];

    /** Insight di un contenuto, validi per i post e per i reel. */
    private const MEDIA_METRICS = [
        'views', 'reach', 'likes', 'comments', 'shares', 'saved', 'total_interactions',
    ];

    /** Metriche in più dei soli reel. */
    private const REELS_METRICS = [
        'ig_reels_avg_watch_time', 'ig_reels_video_view_total_time', 'reels_skip_rate',
    ];

    /** Meta non accetta intervalli più lunghi di 30 giorni sugli insight. */
    private const MAX_WINDOW_DAYS = 30;

    /**
     * Valori di dimensione che Meta usa internamente e che non significano
     * niente per chi legge: comparivano in fondo alle ripartizioni come
     * "Default Do Not Use", con un conteggio a una cifra.
     */
    private const DIMENSIONI_SEGNAPOSTO = ['default_do_not_use', 'unknown_media_type'];

    public function __construct(private readonly MetaClient $client) {}

    /**
     * @return array<string, mixed>
     *
     * @throws MetaException
     */
    public function profile(string $igAccountId, string $token): array
    {
        return Cache::remember(
            "social:ig_profile:{$igAccountId}",
            1800,
            fn (): array => $this->client->get($igAccountId, [
                'fields' => 'id,username,name,profile_picture_url,followers_count,follows_count,media_count,biography,website',
                'access_token' => $token,
            ]),
        );
    }

    /**
     * Metriche `total_value` nell'intervallo [since, until).
     *
     * `follower_count` è escluso apposta: sotto i 100 follower Meta lo rifiuta e
     * farebbe fallire l'intera chiamata.
     *
     * @return array<string, int>
     *
     * @throws MetaException
     */
    public function totals(string $igAccountId, string $token, int $since, int $until): array
    {
        $lastError = null;

        foreach ([self::TOTAL_METRICS, self::TOTAL_METRICS_MINIMAL] as $metrics) {
            try {
                $response = $this->client->get($igAccountId.'/insights', [
                    'metric' => implode(',', $metrics),
                    'period' => 'day',
                    'metric_type' => 'total_value',
                    'since' => $since,
                    'until' => $until,
                    'access_token' => $token,
                ]);
            } catch (MetaException $e) {
                if (! $e->isRetryableWithoutMetric()) {
                    throw $e;
                }

                $lastError = $e;

                continue;
            }

            $out = [];

            foreach ((array) ($response['data'] ?? []) as $metric) {
                if (is_array($metric) && filled($metric['name'] ?? null)) {
                    $out[(string) $metric['name']] = (int) ($metric['total_value']['value'] ?? 0);
                }
            }

            return $out;
        }

        throw $lastError ?? new MetaException('unavailable', 'Insights Instagram non disponibili');
    }

    /**
     * `reach` e `follower_count` giorno per giorno: le uniche due metriche che
     * Meta dà davvero in serie storica.
     *
     * @return array{reach: array<string, int>, follower_count: array<string, int>}
     *
     * @throws MetaException
     */
    public function timeSeries(string $igAccountId, string $token, int $days): array
    {
        $out = ['reach' => [], 'follower_count' => []];
        $remaining = $days + 1;
        $until = time();
        // `follower_count` è rifiutato sotto i 100 follower e trascinerebbe giù
        // anche `reach`: si chiedono insieme e, se salta, si ripete con la sola reach.
        $metrics = 'reach,follower_count';

        while ($remaining > 0) {
            $window = min(self::MAX_WINDOW_DAYS, $remaining);
            $since = $until - $window * 86400;

            $params = [
                'period' => 'day',
                'since' => $since,
                'until' => $until,
                'metric_type' => 'time_series',
                'access_token' => $token,
            ];

            try {
                $response = $this->client->get($igAccountId.'/insights', ['metric' => $metrics] + $params);
            } catch (MetaException $e) {
                if ($metrics === 'reach' || ! $e->isRetryableWithoutMetric()) {
                    throw $e;
                }

                Log::info('Meta: follower_count non disponibile (account sotto i 100 follower?)', [
                    'ig_account_id' => $igAccountId,
                ]);

                $metrics = 'reach';
                $response = $this->client->get($igAccountId.'/insights', ['metric' => $metrics] + $params);
            }

            foreach ((array) ($response['data'] ?? []) as $metric) {
                $name = is_array($metric) ? (string) ($metric['name'] ?? '') : '';

                if (! isset($out[$name])) {
                    continue;
                }

                foreach ((array) ($metric['values'] ?? []) as $value) {
                    $day = self::dayFromEndTime((string) ($value['end_time'] ?? ''));

                    if ($day !== null) {
                        $out[$name][$day] = (int) ($value['value'] ?? 0);
                    }
                }
            }

            $until = $since;
            $remaining -= $window;
        }

        return $out;
    }

    /**
     * Le ripartizioni di periodo: views e reach per tipo di pubblico e di
     * contenuto, tap sui link per bottone.
     *
     * @return array<string, array<string, int>|null>
     */
    public function breakdowns(string $igAccountId, string $token, int $days): array
    {
        return Cache::remember(
            "social:ig_breakdowns:{$igAccountId}:{$days}",
            3600,
            function () use ($igAccountId, $token, $days): array {
                $until = time();
                $since = $until - $days * 86400;

                $jobs = [
                    'views_by_follower_type' => ['views', 'follower_type'],
                    'views_by_media_type' => ['views', 'media_product_type'],
                    'reach_by_media_type' => ['reach', 'media_product_type'],
                    'follows_and_unfollows' => ['follows_and_unfollows', 'follow_type'],
                    'profile_links_by_type' => ['profile_links_taps', 'contact_button_type'],
                ];

                $out = [];

                foreach ($jobs as $key => [$metric, $breakdown]) {
                    $out[$key] = $this->breakdown($igAccountId, $token, $metric, $breakdown, $since, $until);
                }

                return $out;
            },
        );
    }

    /**
     * Demografia dei follower.
     *
     * Meta la calcola solo sopra i 100 follower e solo per i primi 45 valori di
     * ogni voce: sotto soglia non è un errore, è semplicemente assente.
     *
     * @return array<string, array<string, int>|null>|null
     */
    public function demographics(string $igAccountId, string $token): ?array
    {
        return Cache::remember(
            "social:ig_demographics:{$igAccountId}",
            6 * 3600,
            function () use ($igAccountId, $token): ?array {
                $out = [];
                $any = false;

                foreach (['age', 'gender', 'city', 'country'] as $breakdown) {
                    try {
                        $response = $this->client->get($igAccountId.'/insights', [
                            'metric' => 'follower_demographics',
                            'period' => 'lifetime',
                            'metric_type' => 'total_value',
                            'timeframe' => 'last_30_days',
                            'breakdown' => $breakdown,
                            'access_token' => $token,
                        ]);
                    } catch (MetaException $e) {
                        if (! $e->isRetryableWithoutMetric()) {
                            throw $e;
                        }

                        $out[$breakdown] = null;

                        continue;
                    }

                    $values = self::sumBreakdownResults($response, lowercase: false);
                    $out[$breakdown] = $values;
                    $any = $any || $values !== [];
                }

                return $any ? $out : null;
            },
        );
    }

    /**
     * Contenuti recenti con i loro insight.
     *
     * @return list<array<string, mixed>>
     *
     * @throws MetaException
     */
    public function media(string $igAccountId, string $token, int $limit = 12): array
    {
        $fields = 'id,caption,media_type,media_product_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count';

        try {
            $response = $this->client->get($igAccountId.'/media', [
                'fields' => $fields.',insights.metric('.implode(',', self::MEDIA_METRICS).')',
                'limit' => $limit,
                'access_token' => $token,
            ]);
        } catch (MetaException $e) {
            if (! $e->isRetryableWithoutMetric()) {
                throw $e;
            }

            // L'espansione degli insight dentro /media è la via economica: se
            // l'account non la accetta si ripiega sui soli campi del contenuto.
            $response = $this->client->get($igAccountId.'/media', [
                'fields' => $fields,
                'limit' => $limit,
                'access_token' => $token,
            ]);
        }

        $out = [];

        foreach ((array) ($response['data'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $insights = is_array($item['insights']['data'] ?? null)
                ? self::parseInsightRows($item['insights']['data'])
                : [];

            // Mi piace e commenti arrivano anche come campi del contenuto: si usano
            // come rete di sicurezza quando l'insight corrispondente manca.
            $insights['likes'] ??= (int) ($item['like_count'] ?? 0);
            $insights['comments'] ??= (int) ($item['comments_count'] ?? 0);

            if (($item['media_product_type'] ?? '') === 'REELS') {
                $insights += $this->reelsExtras((string) $item['id'], $token);
            }

            $item['insights'] = $insights;
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Insight aggiuntivi di un reel. Assenti non è un problema: sono un di più.
     *
     * @return array<string, int|float>
     */
    private function reelsExtras(string $mediaId, string $token): array
    {
        try {
            $response = $this->client->get($mediaId.'/insights', [
                'metric' => implode(',', self::REELS_METRICS),
                'access_token' => $token,
            ]);
        } catch (MetaException) {
            return [];
        }

        return self::parseInsightRows((array) ($response['data'] ?? []));
    }

    /**
     * Somma un breakdown su finestre di 30 giorni.
     *
     * @return array<string, int>|null null quando Meta rifiuta la metrica
     */
    private function breakdown(string $igAccountId, string $token, string $metric, string $breakdown, int $since, int $until): ?array
    {
        $accumulated = [];
        $cursor = $until;

        while ($cursor > $since) {
            $chunkSince = max($since, $cursor - self::MAX_WINDOW_DAYS * 86400);

            try {
                $response = $this->client->get($igAccountId.'/insights', [
                    'metric' => $metric,
                    'period' => 'day',
                    'metric_type' => 'total_value',
                    'breakdown' => $breakdown,
                    'since' => $chunkSince,
                    'until' => $cursor,
                    'access_token' => $token,
                ]);
            } catch (MetaException $e) {
                if (! $e->isRetryableWithoutMetric()) {
                    throw $e;
                }

                return null;
            }

            foreach (self::sumBreakdownResults($response, lowercase: true) as $dimension => $value) {
                $accumulated[$dimension] = ($accumulated[$dimension] ?? 0) + $value;
            }

            $cursor = $chunkSince;
        }

        arsort($accumulated);

        return $accumulated;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, int>
     */
    private static function sumBreakdownResults(array $response, bool $lowercase): array
    {
        $values = [];

        foreach ((array) ($response['data'] ?? []) as $metric) {
            foreach ((array) ($metric['total_value']['breakdowns'] ?? []) as $breakdown) {
                foreach ((array) ($breakdown['results'] ?? []) as $result) {
                    $dimension = (string) (($result['dimension_values'] ?? ['?'])[0] ?? '?');
                    $dimension = $lowercase ? mb_strtolower($dimension) : $dimension;

                    if (in_array(mb_strtolower($dimension), self::DIMENSIONI_SEGNAPOSTO, true)) {
                        continue;
                    }

                    $values[$dimension] = ($values[$dimension] ?? 0) + (int) ($result['value'] ?? 0);
                }
            }
        }

        arsort($values);

        return $values;
    }

    /**
     * Righe di insight → mappa nome ⇒ valore.
     *
     * @param  array<int, mixed>  $rows
     * @return array<string, int|float>
     */
    public static function parseInsightRows(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! filled($row['name'] ?? null)) {
                continue;
            }

            $value = $row['values'][0]['value'] ?? $row['total_value']['value'] ?? $row['value'] ?? 0;
            $out[(string) $row['name']] = is_numeric($value) ? $value + 0 : 0;
        }

        return $out;
    }

    /**
     * `end_time` è la mezzanotte SUCCESSIVA nel fuso dell'account, espressa in
     * UTC: per un account italiano d'estate è 22:00Z del giorno prima.
     * Togliendo dodici ore si cade dentro il giorno giusto per qualunque fuso.
     */
    private static function dayFromEndTime(string $endTime): ?string
    {
        $timestamp = strtotime($endTime);

        if ($timestamp === false) {
            return $endTime === '' ? null : mb_substr($endTime, 0, 10);
        }

        return gmdate('Y-m-d', $timestamp - 12 * 3600);
    }
}

<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Cache;

/**
 * Le metriche della Pagina Facebook.
 *
 * Ogni metrica è una chiamata a sé, e non per pigrizia: Meta ne ha ritirate a
 * ondate fra il 2024 e il 2026, e chiedendole insieme una sola metrica morta
 * azzererebbe tutte le altre. Chiedendole separate, quella deprecata finisce in
 * `unavailable` e la pagina la mostra come "n/d".
 *
 * Il caso davvero insidioso è un altro: senza il permesso `read_insights` la
 * Graph API NON risponde con un errore, risponde con `data: []` su ogni
 * metrica. Zero attività e permesso mancante si assomigliano; si distinguono
 * solo osservando che *tutte* le metriche accettate sono tornate vuote, ed è
 * quello che fa `missing_read_insights`.
 */
class FacebookPageInsights
{
    /**
     * Metriche giornaliere da sommare sul periodo.
     *
     * `page_media_view` e `page_total_media_view_unique` hanno preso il posto di
     * `page_impressions` e `page_impressions_unique`, ritirate da Meta.
     */
    private const SUM_METRICS = [
        'new_follows' => 'page_daily_follows',
        'page_views' => 'page_views_total',
        'media_views' => 'page_media_view',
        'unique_viewers' => 'page_total_media_view_unique',
        'post_engagements' => 'page_post_engagements',
        'video_views' => 'page_video_views',
    ];

    public function __construct(private readonly MetaClient $client) {}

    /**
     * @return array<string, mixed>
     *
     * @throws MetaException
     */
    public function forPage(string $pageId, string $token, int $days): array
    {
        return Cache::remember(
            "social:fb_page:{$pageId}:{$days}",
            3600,
            fn (): array => $this->fetch($pageId, $token, $days),
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MetaException
     */
    private function fetch(string $pageId, string $token, int $days): array
    {
        $since = now()->subDays($days)->toDateString();
        $until = now()->toDateString();

        $result = [
            'followers' => null,
            'fans' => null,
            'new_follows' => null,
            'page_views' => null,
            'media_views' => null,
            'unique_viewers' => null,
            'post_engagements' => null,
            'video_views' => null,
            'reactions' => null,
            'link' => null,
            'unavailable' => [],
        ];

        try {
            $page = $this->client->get($pageId, [
                'fields' => 'name,followers_count,fan_count,link',
                'access_token' => $token,
            ]);

            $result['followers'] = isset($page['followers_count']) ? (int) $page['followers_count'] : null;
            $result['fans'] = isset($page['fan_count']) ? (int) $page['fan_count'] : null;
            $result['link'] = $page['link'] ?? null;
        } catch (MetaException $e) {
            if (! $e->isRetryableWithoutMetric()) {
                throw $e;
            }

            $result['unavailable'][] = 'followers';
        }

        $empty = 0;
        $rejected = 0;

        foreach (self::SUM_METRICS as $key => $metric) {
            $values = $this->metricValues($pageId, $token, $metric, $since, $until);

            if ($values === null) {
                $rejected++;
                $result['unavailable'][] = $key;

                continue;
            }

            if ($values === []) {
                $empty++;
                $result['unavailable'][] = $key;

                continue;
            }

            $result[$key] = array_sum(array_map(
                static fn (array $value): int => (int) ($value['value'] ?? 0),
                $values,
            ));
        }

        $reactions = $this->metricValues($pageId, $token, 'page_actions_post_reactions_total', $since, $until);

        if ($reactions === null || $reactions === []) {
            $result['unavailable'][] = 'reactions';
            $empty += $reactions === [] ? 1 : 0;
        } else {
            $accumulated = [];

            foreach ($reactions as $value) {
                foreach ((array) ($value['value'] ?? []) as $type => $count) {
                    $accumulated[(string) $type] = ($accumulated[(string) $type] ?? 0) + (int) $count;
                }
            }

            arsort($accumulated);
            $result['reactions'] = $accumulated;
        }

        // Tutte le metriche che Meta ha accettato sono tornate vuote: è il
        // permesso che manca, non l'assenza di attività.
        $result['missing_read_insights'] = $empty > 0 && ($empty + $rejected) >= count(self::SUM_METRICS);

        return $result;
    }

    /**
     * Valori giornalieri di una metrica.
     *
     * @return list<array<string, mixed>>|null null quando Meta rifiuta la metrica
     *
     * @throws MetaException
     */
    private function metricValues(string $pageId, string $token, string $metric, string $since, string $until): ?array
    {
        try {
            $response = $this->client->get($pageId.'/insights', [
                'metric' => $metric,
                'period' => 'day',
                'since' => $since,
                'until' => $until,
                'access_token' => $token,
            ]);
        } catch (MetaException $e) {
            if (! $e->isRetryableWithoutMetric()) {
                throw $e;
            }

            return null;
        }

        foreach ((array) ($response['data'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['name'] ?? '') === $metric) {
                return array_values(array_filter(
                    (array) ($entry['values'] ?? []),
                    'is_array',
                ));
            }
        }

        return [];
    }
}

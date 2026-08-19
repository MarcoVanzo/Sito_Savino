<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Il payload della pagina "Social Analytics" per un account collegato.
 *
 * Mette insieme quattro fonti che si comportano in modo diverso — profilo,
 * aggregati di periodo, serie giornaliera ricostruita, Pagina Facebook — e le
 * riduce a una struttura sola. Come per le analytics del sito, verso l'esterno
 * non lancia eccezioni: un guasto diventa `error` e la pagina resta in piedi
 * mostrando quello che è riuscita a leggere.
 */
class SocialAnalyticsService
{
    public const ALLOWED_DAYS = [7, 14, 28, 90];

    private const CACHE_TTL = 3600;

    /** @var array<string, array<string, mixed>> */
    private array $memo = [];

    public function __construct(
        private readonly InstagramInsights $instagram,
        private readonly FacebookPageInsights $facebook,
        private readonly InstagramDailySync $dailySync,
    ) {}

    /** @return Collection<int, SocialAccount> */
    public function accounts()
    {
        return SocialAccount::query()->ordered()->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(SocialAccount $account, int $days): array
    {
        $days = in_array($days, self::ALLOWED_DAYS, true) ? $days : 28;
        $memoKey = $account->id.':'.$days;

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        if (! $account->isConnected()) {
            return $this->memo[$memoKey] = $this->failure($account, $days, 'not_connected');
        }

        try {
            $data = $this->build($account, $days);
        } catch (MetaException $e) {
            $data = $this->failure($account, $days, $e->reason, $e->userMessage());
            // La serie salvata resta leggibile: meglio un grafico vecchio che una
            // pagina vuota.
            $data['daily'] = $this->dailySync->series($account, $days);
        }

        return $this->memo[$memoKey] = $data;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MetaException
     */
    private function build(SocialAccount $account, int $days): array
    {
        $token = (string) $account->access_token;
        $data = $this->skeleton($account, $days);

        if ($account->hasInstagram()) {
            $igId = (string) $account->ig_account_id;

            $data['profile'] = $this->instagram->profile($igId, $token);
            $data['breakdowns'] = $this->instagram->breakdowns($igId, $token, $days);
            $data['demographics'] = $this->instagram->demographics($igId, $token);
            $data['posts'] = $this->instagram->media($igId, $token);
            $data['top_posts'] = self::rankPosts($data['posts']);

            $sync = $this->dailySync->fill($account, $days);
            $data['daily'] = $this->dailySync->series($account, $days);
            $data['pending_days'] = $sync['pending'];

            $data['totals'] = $this->periodTotals($account, $days, $data['breakdowns'], $data['daily']);
        }

        if (filled($account->page_id)) {
            $data['facebook'] = $this->facebook->forPage((string) $account->page_id, $token, $days);
        }

        return $data;
    }

    /**
     * Aggregati del periodo.
     *
     * Non si sommano i giorni: `total_value` su tutto l'intervallo è il numero
     * che Meta considera corretto, e per le metriche di persone (reach, account
     * che hanno interagito) sommare i giorni conterebbe più volte la stessa
     * persona. La serie giornaliera serve al grafico, non ai totali.
     *
     * @param  array<string, array<string, int>|null>  $breakdowns
     * @param  list<array<string, mixed>>  $daily
     * @return array<string, int|null>
     */
    private function periodTotals(SocialAccount $account, int $days, array $breakdowns, array $daily): array
    {
        $totals = Cache::remember(
            "social:ig_totals:{$account->id}:{$days}",
            self::CACHE_TTL,
            function () use ($account, $days): array {
                $until = CarbonImmutable::now($this->timezone())->startOfDay();
                $since = $until->subDays($days);

                return $this->instagram->totals(
                    (string) $account->ig_account_id,
                    (string) $account->access_token,
                    $since->getTimestamp(),
                    $until->getTimestamp(),
                );
            },
        );

        $follows = $breakdowns['follows_and_unfollows'] ?? null;
        $reachByType = $breakdowns['reach_by_media_type'] ?? null;

        return [
            'views' => (int) ($totals['views'] ?? 0),
            'reach' => $reachByType === null ? null : array_sum($reachByType),
            'likes' => (int) ($totals['likes'] ?? 0),
            'comments' => (int) ($totals['comments'] ?? 0),
            'shares' => (int) ($totals['shares'] ?? 0),
            'saves' => (int) ($totals['saves'] ?? 0),
            'reposts' => (int) ($totals['reposts'] ?? 0),
            'replies' => (int) ($totals['replies'] ?? 0),
            'total_interactions' => (int) ($totals['total_interactions'] ?? 0),
            'accounts_engaged' => (int) ($totals['accounts_engaged'] ?? 0),
            'profile_links_taps' => (int) ($totals['profile_links_taps'] ?? 0),
            'new_follows' => $follows === null ? null : (int) ($follows['follower'] ?? 0),
            // Meta chiama `non_follower` chi ha smesso di seguire, non
            // `unfollower`: leggendo la chiave sbagliata i persi risultavano
            // sempre zero, e con 1.442 nuovi follower e il saldo in negativo
            // era l'unico numero a non tornare.
            'unfollows' => $follows === null ? null : (int) ($follows['non_follower'] ?? 0),
            'follower_delta' => self::followerDelta($daily),
        ];
    }

    /**
     * Variazione dei follower nel periodo, dal primo giorno noto all'ultimo.
     *
     * I giorni senza dato valgono zero in archivio e vanno saltati, altrimenti
     * il confronto partirebbe da un finto zero e mostrerebbe una crescita
     * inventata.
     *
     * @param  list<array<string, mixed>>  $daily
     */
    private static function followerDelta(array $daily): ?int
    {
        $known = array_values(array_filter(
            array_map(static fn (array $row): int => (int) ($row['follower_count'] ?? 0), $daily),
            static fn (int $value): bool => $value > 0,
        ));

        if (count($known) < 2) {
            return null;
        }

        return end($known) - $known[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function skeleton(SocialAccount $account, int $days): array
    {
        return [
            'ok' => true,
            'error' => null,
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'page_name' => $account->page_name,
                'ig_username' => $account->ig_username,
                'has_instagram' => $account->hasInstagram(),
            ],
            'period' => ['days' => $days],
            'profile' => [],
            'totals' => [],
            'daily' => [],
            'pending_days' => 0,
            'breakdowns' => [],
            'demographics' => null,
            'posts' => [],
            'top_posts' => [],
            'facebook' => null,
        ];
    }

    /**
     * I contenuti che hanno reso di più, in ordine di interazioni.
     *
     * Il tasso è sulle persone raggiunte e non sulle visualizzazioni: dice
     * quanti di quelli che hanno visto il post hanno poi fatto qualcosa, che è
     * la domanda a cui serve rispondere quando si decide cosa ripubblicare.
     *
     * @param  list<array<string, mixed>>  $posts
     * @return list<array<string, mixed>>
     */
    private static function rankPosts(array $posts, int $limit = 5): array
    {
        $ranked = array_map(static function (array $post): array {
            $insights = $post['insights'] ?? [];
            $reach = (int) ($insights['reach'] ?? 0);
            $interactions = (int) ($insights['total_interactions'] ?? 0);

            return $post + [
                'rank_interactions' => $interactions,
                'rank_rate' => $reach > 0 ? round($interactions / $reach * 100, 1) : null,
            ];
        }, $posts);

        usort($ranked, static fn (array $a, array $b): int => $b['rank_interactions'] <=> $a['rank_interactions']);

        return array_slice($ranked, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(SocialAccount $account, int $days, string $reason, ?string $message = null): array
    {
        $data = $this->skeleton($account, $days);
        $data['ok'] = false;
        $data['error'] = [
            'reason' => $reason,
            'message' => $message ?? (new MetaException($reason, ''))->userMessage(),
        ];

        return $data;
    }

    private function timezone(): string
    {
        return (string) config('services.meta.timezone', 'Europe/Rome');
    }
}

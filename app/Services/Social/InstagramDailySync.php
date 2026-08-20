<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialInsightDaily;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Ricostruisce la serie giorno per giorno degli insight Instagram.
 *
 * Serve perché la Graph API non la fornisce: le metriche che interessano
 * (`views`, `total_interactions`, `accounts_engaged`…) esistono solo come
 * `total_value`, cioè un numero unico per l'intervallo richiesto. L'unico modo
 * di avere il valore di martedì è chiedere l'intervallo "solo martedì". Una
 * chiamata per giorno, quindi: si fa una volta sola e si conserva.
 *
 * Da qui il tetto alle chiamate. Meta concede circa 200 richieste l'ora per
 * utente, e la richiesta HTTP deve comunque chiudersi in tempi umani: aprendo
 * la pagina si riempiono pochi giorni per volta, mentre il grosso lo fa il job
 * notturno, che ha tutto il tempo e un budget più largo.
 */
class InstagramDailySync
{
    /** Giorni riempiti al massimo mentre qualcuno guarda la pagina. */
    public const MAX_CALLS_ON_DEMAND = 15;

    /** Giorni riempiti al massimo dal comando schedulato. */
    public const MAX_CALLS_JOB = 120;

    public function __construct(private readonly InstagramInsights $instagram) {}

    /**
     * Riempie i giorni mancanti e restituisce quanti ne restano.
     *
     * @return array{filled: int, pending: int}
     */
    public function fill(SocialAccount $account, int $days, int $maxCalls = self::MAX_CALLS_ON_DEMAND): array
    {
        if (! $account->hasInstagram() || ! $account->isConnected()) {
            return ['filled' => 0, 'pending' => 0];
        }

        $wanted = $this->dayRange($days);
        $known = SocialInsightDaily::query()
            ->where('social_account_id', $account->id)
            ->whereIn('day', $wanted)
            ->get()
            ->keyBy(fn (SocialInsightDaily $row): string => $row->day->format('Y-m-d'));

        // Un giorno già definitivo non si richiede mai più: è il motivo per cui
        // il costo di questa pagina cala a zero dopo i primi giorni.
        $todo = array_values(array_filter($wanted, static function (string $day) use ($known): bool {
            $row = $known->get($day);

            return $row === null || ! $row->is_final;
        }));

        // Dal più recente: è la parte di grafico che si guarda per prima.
        $todo = array_reverse($todo);
        $budget = array_slice($todo, 0, $maxCalls);
        $filled = 0;

        foreach ($budget as $day) {
            try {
                $start = CarbonImmutable::parse($day, $this->timezone())->startOfDay();

                $totals = $this->instagram->totals(
                    (string) $account->ig_account_id,
                    (string) $account->access_token,
                    $start->getTimestamp(),
                    $start->addDay()->getTimestamp(),
                );
            } catch (MetaException $e) {
                // Rate limit o token morto: si smette subito, riprende il job.
                Log::info('Meta: riempimento della serie giornaliera interrotto', [
                    'social_account_id' => $account->id,
                    'day' => $day,
                    'reason' => $e->reason,
                ]);

                break;
            }

            $this->store($account, $day, $totals);
            $filled++;
        }

        $this->applyTimeSeries($account, $days);

        $account->forceFill(['last_synced_at' => now()])->save();

        return ['filled' => $filled, 'pending' => max(0, count($todo) - $filled)];
    }

    /**
     * La serie salvata, un elemento per giorno del periodo (zeri compresi).
     *
     * @return list<array<string, mixed>>
     */
    public function series(SocialAccount $account, int $days): array
    {
        $wanted = $this->dayRange($days);

        $saved = SocialInsightDaily::query()
            ->where('social_account_id', $account->id)
            ->whereIn('day', $wanted)
            ->get()
            ->keyBy(fn (SocialInsightDaily $row): string => $row->day->format('Y-m-d'));

        $columns = [
            'reach', 'views', 'follower_count', 'likes', 'comments', 'shares', 'saves',
            'reposts', 'replies', 'total_interactions', 'accounts_engaged', 'profile_links_taps',
        ];

        $out = [];

        foreach ($wanted as $day) {
            $row = $saved->get($day);
            $entry = ['day' => $day];

            foreach ($columns as $column) {
                $entry[$column] = $row === null ? 0 : (int) $row->{$column};
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * `reach` e `follower_count` arrivano già in serie storica: una chiamata
     * copre trenta giorni, quindi si aggiornano sempre, anche sui giorni che il
     * budget non ha toccato.
     */
    private function applyTimeSeries(SocialAccount $account, int $days): void
    {
        try {
            $series = $this->instagram->timeSeries(
                (string) $account->ig_account_id,
                (string) $account->access_token,
                $days,
            );
        } catch (MetaException $e) {
            Log::info('Meta: serie reach/follower non disponibile', [
                'social_account_id' => $account->id,
                'reason' => $e->reason,
            ]);

            return;
        }

        $seriesDays = array_unique(array_merge(
            array_keys($series['reach']),
            array_keys($series['follower_count']),
        ));

        foreach ($seriesDays as $day) {
            $values = array_filter([
                'reach' => $series['reach'][$day] ?? null,
                'follower_count' => $series['follower_count'][$day] ?? null,
            ], static fn ($value): bool => $value !== null);

            if ($values === []) {
                continue;
            }

            // `is_final` NON si tocca qui: lo mette `store()`, che è l'unico a
            // scaricare i totali del giorno. Marcando definitivo un giorno di
            // cui si conosce solo la reach, il filtro dei giorni da chiedere lo
            // salterebbe per sempre e views, interazioni e account raggiunti
            // resterebbero a zero — cosa che succedeva a tutti i giorni oltre
            // il tetto di chiamate della prima apertura della pagina.
            SocialInsightDaily::query()->updateOrCreate(
                ['social_account_id' => $account->id, 'day' => $day],
                $values + ['ig_account_id' => (string) $account->ig_account_id],
            );
        }
    }

    /**
     * @param  array<string, int>  $totals
     */
    private function store(SocialAccount $account, string $day, array $totals): void
    {
        SocialInsightDaily::query()->updateOrCreate(
            ['social_account_id' => $account->id, 'day' => $day],
            [
                'ig_account_id' => (string) $account->ig_account_id,
                'views' => max(0, (int) ($totals['views'] ?? 0)),
                'likes' => max(0, (int) ($totals['likes'] ?? 0)),
                'comments' => max(0, (int) ($totals['comments'] ?? 0)),
                'shares' => max(0, (int) ($totals['shares'] ?? 0)),
                'saves' => max(0, (int) ($totals['saves'] ?? 0)),
                'reposts' => max(0, (int) ($totals['reposts'] ?? 0)),
                'replies' => max(0, (int) ($totals['replies'] ?? 0)),
                'total_interactions' => max(0, (int) ($totals['total_interactions'] ?? 0)),
                'accounts_engaged' => max(0, (int) ($totals['accounts_engaged'] ?? 0)),
                'profile_links_taps' => max(0, (int) ($totals['profile_links_taps'] ?? 0)),
                'is_final' => $this->isFinal($day),
            ],
        );
    }

    /**
     * Giorni del periodo, dal più vecchio al più recente, nel fuso dell'account.
     *
     * @return list<string>
     */
    public function dayRange(int $days): array
    {
        $today = $this->today();
        $out = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $out[] = $today->modify("-{$i} days")->format('Y-m-d');
        }

        return $out;
    }

    private function isFinal(string $day): bool
    {
        $delay = (int) config('services.meta.data_delay_days', 2);

        return $day <= $this->today()->modify("-{$delay} days")->format('Y-m-d');
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone($this->timezone()));
    }

    private function timezone(): string
    {
        return (string) config('services.meta.timezone', 'Europe/Rome');
    }
}

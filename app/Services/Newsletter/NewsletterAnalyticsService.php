<?php

namespace App\Services\Newsletter;

use App\Models\NewsletterSubscriber;
use Carbon\CarbonImmutable;

/**
 * I dati della pagina "Newsletter".
 *
 * Le due metà hanno affidabilità diverse ed è giusto che si vedano separate:
 * gli iscritti li conosciamo per certo, perché la lista locale è la fonte da
 * cui parte la sincronizzazione; i risultati delle campagne (aperture, click,
 * disiscrizioni) li conosce solo ActiveCampaign, che li invia. Se la seconda
 * metà non arriva, la prima resta.
 */
class NewsletterAnalyticsService
{
    public const ALLOWED_DAYS = [7, 28, 90, 365];

    public function __construct(private readonly ActiveCampaignReports $reports) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(int $days): array
    {
        $days = in_array($days, self::ALLOWED_DAYS, true) ? $days : 28;
        $from = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $campaigns = $this->reports->campaigns();

        return [
            'period' => ['days' => $days, 'start' => $from->toDateString()],
            'subscribers' => $this->subscriberStats($from),
            'daily' => $this->subscriptionsPerDay($from, $days),
            'campaigns' => $campaigns['campaigns'],
            'campaigns_ok' => $campaigns['ok'],
            'campaigns_message' => $campaigns['message'] ?? null,
            'averages' => self::averages($campaigns['campaigns']),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function subscriberStats(CarbonImmutable $from): array
    {
        return [
            'total' => NewsletterSubscriber::query()->count(),
            'active' => NewsletterSubscriber::query()->whereNull('unsubscribed_at')->count(),
            'unsubscribed' => NewsletterSubscriber::query()->whereNotNull('unsubscribed_at')->count(),
            'new_in_period' => NewsletterSubscriber::query()->where('created_at', '>=', $from)->count(),
            // Un iscritto non sincronizzato è un contatto che non riceverà la
            // prossima campagna: è un guasto da vedere, non una statistica.
            'not_synced' => NewsletterSubscriber::query()
                ->whereNull('unsubscribed_at')
                ->where('synced_to_ac', false)
                ->count(),
        ];
    }

    /**
     * Nuove iscrizioni giorno per giorno, zeri compresi.
     *
     * @return list<array{day: string, subscriptions: int}>
     */
    private function subscriptionsPerDay(CarbonImmutable $from, int $days): array
    {
        $counts = NewsletterSubscriber::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $out = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i)->toDateString();
            $out[] = ['day' => $day, 'subscriptions' => (int) ($counts[$day] ?? 0)];
        }

        return $out;
    }

    /**
     * Medie sulle campagne del periodo mostrato, pesate sugli invii: una
     * campagna da 50 destinatari non deve contare quanto una da 5.000.
     *
     * @param  list<array<string, mixed>>  $campaigns
     * @return array{open_rate: float|null, click_rate: float|null, sent: int}
     */
    private static function averages(array $campaigns): array
    {
        $sent = 0;
        $opens = 0;
        $clicks = 0;

        foreach ($campaigns as $campaign) {
            $sent += (int) $campaign['sent'];
            $opens += (int) $campaign['unique_opens'];
            $clicks += (int) $campaign['unique_clicks'];
        }

        return [
            'sent' => $sent,
            'open_rate' => $sent > 0 ? round($opens / $sent * 100, 1) : null,
            'click_rate' => $sent > 0 ? round($clicks / $sent * 100, 1) : null,
        ];
    }
}

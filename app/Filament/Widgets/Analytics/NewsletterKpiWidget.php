<?php

namespace App\Filament\Widgets\Analytics;

use App\Services\Newsletter\NewsletterAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Iscritti e rendimento medio delle campagne.
 */
class NewsletterKpiWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    public int $days = 28;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $data = app(NewsletterAnalyticsService::class)->overview($this->days);
        $subscribers = $data['subscribers'];
        $averages = $data['averages'];

        $stats = [
            Stat::make('Iscritti attivi', self::number($subscribers['active']))
                ->icon('heroicon-m-users')
                ->description($subscribers['unsubscribed'].' disiscritti in totale'),

            Stat::make('Nuovi iscritti', self::number($subscribers['new_in_period']))
                ->icon('heroicon-m-user-plus')
                ->description('Nel periodo selezionato')
                ->color('success')
                ->chart(array_map(static fn (array $row): float => (float) $row['subscriptions'], $data['daily'])),

            Stat::make('Destinatari raggiunti', self::number($averages['sent']))
                ->icon('heroicon-m-paper-airplane')
                ->description('Somma degli invii delle ultime campagne'),

            Stat::make('Tasso di apertura', $averages['open_rate'] === null ? 'n/d' : self::percent($averages['open_rate']))
                ->icon('heroicon-m-envelope-open')
                ->description('Medio, pesato sugli invii'),

            Stat::make('Tasso di click', $averages['click_rate'] === null ? 'n/d' : self::percent($averages['click_rate']))
                ->icon('heroicon-m-cursor-arrow-rays')
                ->description('Medio, pesato sugli invii'),
        ];

        // Un iscritto non sincronizzato non riceverà la prossima campagna: è un
        // guasto da vedere subito, e si mostra solo quando esiste.
        if ($subscribers['not_synced'] > 0) {
            $stats[] = Stat::make('Non sincronizzati', self::number($subscribers['not_synced']))
                ->icon('heroicon-m-exclamation-triangle')
                ->description('Iscritti che ActiveCampaign non ha ancora')
                ->color('danger');
        }

        return $stats;
    }

    private static function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private static function percent(float $value): string
    {
        return number_format($value, 1, ',', '.').'%';
    }
}

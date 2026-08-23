<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\HasAnalyticsPeriod;
use App\Services\Newsletter\NewsletterAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Iscritti e rendimento medio delle campagne.
 */
class NewsletterKpiWidget extends BaseWidget
{
    use HasAnalyticsPeriod;

    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /** Palette dell'identità visiva, ripetuta in ordine sulle schede. */
    private const ACCENTI = ['#ED028C', '#10B981', '#94A3B8', '#0EA5E9', '#003063', '#C9A84C', '#F97316'];

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $data = app(NewsletterAnalyticsService::class)->overview($this->days);
        $subscribers = $data['subscribers'];
        $averages = $data['averages'];

        $stats = [
            Stat::make('Iscritti totali', self::number($subscribers['total']))
                ->extraAttributes(self::accento(0))
                ->icon('heroicon-m-users')
                ->description('In archivio, storico completo'),

            Stat::make('Attivi', self::number($subscribers['active']))
                ->extraAttributes(self::accento(1))
                ->icon('heroicon-m-check-circle')
                ->description('Riceveranno la prossima campagna')
                ->color('success'),

            Stat::make('Disiscritti', self::number($subscribers['unsubscribed']))
                ->extraAttributes(self::accento(2))
                ->icon('heroicon-m-x-circle')
                ->description('Non vanno più contattati')
                ->color('gray'),

            Stat::make('Nuovi iscritti', self::number($subscribers['new_in_period']))
                ->extraAttributes(self::accento(3))
                ->icon('heroicon-m-user-plus')
                ->description('Nel periodo selezionato')
                ->color('success')
                ->chart(array_map(static fn (array $row): float => (float) $row['subscriptions'], $data['daily'])),

            Stat::make('Destinatari raggiunti', self::number($averages['sent']))
                ->extraAttributes(self::accento(4))
                ->icon('heroicon-m-paper-airplane')
                ->description('Somma degli invii delle ultime campagne'),

            Stat::make('Tasso di apertura', $averages['open_rate'] === null ? 'n/d' : self::percent($averages['open_rate']))
                ->extraAttributes(self::accento(5))
                ->icon('heroicon-m-envelope-open')
                ->description('Medio, pesato sugli invii'),

            Stat::make('Tasso di click', $averages['click_rate'] === null ? 'n/d' : self::percent($averages['click_rate']))
                ->extraAttributes(self::accento(6))
                ->icon('heroicon-m-cursor-arrow-rays')
                ->description('Medio, pesato sugli invii'),
        ];

        // Un iscritto non sincronizzato non riceverà la prossima campagna: è un
        // guasto da vedere subito, e si mostra solo quando esiste.
        if ($subscribers['not_synced'] > 0) {
            $stats[] = Stat::make('Non sincronizzati', self::number($subscribers['not_synced']))
                ->extraAttributes(self::accento(7))
                ->icon('heroicon-m-exclamation-triangle')
                ->description('Iscritti che ActiveCampaign non ha ancora')
                ->color('danger');
        }

        return $stats;
    }

    /**
     * Barra colorata in cima alla scheda. In linea e non nel tema del pannello,
     * che è condiviso con dashboard e shop e li colorerebbe tutti.
     *
     * @return array<string, string>
     */
    private static function accento(int $indice): array
    {
        return ['style' => 'border-top: 3px solid '.self::ACCENTI[$indice % count(self::ACCENTI)].';'];
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

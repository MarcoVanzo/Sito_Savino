<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\HasAnalyticsPeriod;
use App\Services\Newsletter\NewsletterAnalyticsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Nuove iscrizioni giorno per giorno.
 *
 * A barre e non a linee: sono conteggi di eventi discreti, spesso a zero, e una
 * linea che scende e risale fra gli zeri suggerisce una continuità che non c'è.
 */
class NewsletterTrendWidget extends ChartWidget
{
    use HasAnalyticsPeriod;

    protected static ?string $heading = 'Nuove iscrizioni';

    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $daily = app(NewsletterAnalyticsService::class)->overview($this->days)['daily'];

        return [
            'datasets' => [
                [
                    'label' => 'Iscrizioni',
                    'data' => array_map(static fn (array $row): int => $row['subscriptions'], $daily),
                    'backgroundColor' => '#003063',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_map(
                static fn (array $row): string => Carbon::parse($row['day'])->format('d/m'),
                $daily,
            ),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}

<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\HasAnalyticsPeriod;
use App\Services\Newsletter\NewsletterAnalyticsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Quanti destinatari ha raggiunto ogni campagna.
 *
 * Letto insieme ai tassi spiega i picchi: un tasso di apertura altissimo su una
 * barra bassa è quasi sempre una prova interna, non un successo.
 */
class NewsletterVolumeWidget extends ChartWidget
{
    use HasAnalyticsPeriod;

    protected static ?string $heading = 'Volumi di invio';

    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $series = app(NewsletterAnalyticsService::class)->overview($this->days)['campaign_series'];

        return [
            'datasets' => [
                [
                    'label' => 'Destinatari',
                    'data' => array_map(static fn (array $c): int => $c['sent'], $series),
                    'backgroundColor' => '#003063',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_map(
                static fn (array $c): string => $c['sent_at'] ? Carbon::parse($c['sent_at'])->format('d/m') : '—',
                $series,
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

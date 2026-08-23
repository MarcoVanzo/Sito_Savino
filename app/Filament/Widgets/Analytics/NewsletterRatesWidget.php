<?php

namespace App\Filament\Widgets\Analytics;

use App\Services\Newsletter\NewsletterAnalyticsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

/**
 * Aperture e click campagna per campagna, in percentuale.
 *
 * In percentuale e non in valore assoluto perché il confronto che interessa è
 * fra campagne di dimensione diversa: una da 300 destinatari e una da 3.000 non
 * si leggono sulla stessa scala, i loro tassi sì.
 */
class NewsletterRatesWidget extends ChartWidget
{
    protected static ?string $heading = 'Trend di apertura e click';

    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 1;

    /*
     * I valori arrivano da getWidgetData() della pagina. #[Reactive] non è
     * decorativo: senza, Livewire applica i mount param una sola volta e il
     * widget resta fermo al periodo con cui è stato montato — la pagina cambia,
     * i numeri no. È il meccanismo che Filament usa nel suo
     * InteractsWithPageFilters.
     */
    #[Reactive]
    public int $days = 28;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $series = app(NewsletterAnalyticsService::class)->overview($this->days)['campaign_series'];

        return [
            'datasets' => [
                [
                    'label' => 'Aperture (%)',
                    'data' => array_map(static fn (array $c): ?float => $c['open_rate'], $series),
                    'borderColor' => '#ED028C',
                    'backgroundColor' => 'rgba(237, 2, 140, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'Click (%)',
                    'data' => array_map(static fn (array $c): ?float => $c['click_rate'], $series),
                    'borderColor' => '#C9A84C',
                    'backgroundColor' => 'rgba(201, 168, 76, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
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
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'max' => 100, 'ticks' => ['callback' => null]]],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }
}

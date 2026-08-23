<?php

namespace App\Filament\Widgets\Analytics;

use App\Models\AnalyticsSite;
use App\Services\Analytics\WebAnalyticsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

/**
 * Utenti e visualizzazioni giorno per giorno.
 */
class WebTrendWidget extends ChartWidget
{
    protected static ?string $heading = 'Andamento giornaliero';

    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '280px';

    /*
     * I valori arrivano da getWidgetData() della pagina. #[Reactive] non è
     * decorativo: senza, Livewire applica i mount param una sola volta e il
     * widget resta fermo al periodo con cui è stato montato — la pagina cambia,
     * i numeri no. È il meccanismo che Filament usa nel suo
     * InteractsWithPageFilters.
     */
    #[Reactive]
    public ?int $siteId = null;

    #[Reactive]
    public int $days = 28;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $site = $this->siteId === null ? null : AnalyticsSite::query()->find($this->siteId);

        if ($site === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $daily = app(WebAnalyticsService::class)->overview($site, $this->days)['daily'];

        return [
            'datasets' => [
                [
                    'label' => 'Utenti',
                    'data' => array_map(static fn (array $row): int => (int) $row['active_users'], $daily),
                    'borderColor' => '#003063',
                    'backgroundColor' => 'rgba(0, 48, 99, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Visualizzazioni',
                    'data' => array_map(static fn (array $row): int => (int) $row['page_views'], $daily),
                    'borderColor' => '#ED028C',
                    'backgroundColor' => 'rgba(237, 2, 140, 0.10)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            // Con 90 giorni le etichette si accavallano: si scrive solo il giorno
            // e il mese, e Chart.js dirada da sé quelle che non ci stanno.
            'labels' => array_map(
                static fn (array $row): string => Carbon::parse($row['day'])->format('d/m'),
                $daily,
            ),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true]],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }
}

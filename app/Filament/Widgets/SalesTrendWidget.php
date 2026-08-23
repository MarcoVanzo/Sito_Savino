<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class SalesTrendWidget extends ChartWidget
{
    protected static ?string $heading = 'Trend Vendite';

    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '350px';

    /**
     * Periodo in giorni, passato da ShopAnalyticsPage::getWidgetData(). #[Reactive]
     * è indispensabile: senza, Livewire monta il widget una volta sola e il
     * cambio di periodo sulla pagina non arriverebbe mai qui.
     */
    #[Reactive]
    public int $period = 30;

    /**
     * Stati che identificano un ordine "pagato".
     */
    private const PAID_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Processing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
    ];

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = $this->period;
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        $period = CarbonPeriod::create($from, $to);

        $revenueByDay = Order::whereIn('status', self::PAID_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, SUM(total_price) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->pluck('revenue', 'date')
            ->toArray();

        $ordersByDay = Order::whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date')
            ->toArray();

        $labels = [];
        $revenueData = [];
        $ordersData = [];

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');
            $revenueData[] = round((float) ($revenueByDay[$key] ?? 0), 2);
            $ordersData[] = (int) ($ordersByDay[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Fatturato (€)',
                    'data' => $revenueData,
                    'borderColor' => '#003063',
                    'backgroundColor' => 'rgba(0, 48, 99, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'N° Ordini',
                    'data' => $ordersData,
                    'borderColor' => '#C9A84C',
                    'backgroundColor' => 'rgba(201, 168, 76, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'position' => 'left',
                    'ticks' => [
                        'callback' => '(value) => "€" + value',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}

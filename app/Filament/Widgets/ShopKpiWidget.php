<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ShopEvent;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ShopKpiWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    /**
     * Stati che identificano un ordine "pagato" (fatturato effettivo).
     */
    private const PAID_STATUSES = [
        OrderStatus::Paid,
        OrderStatus::Processing,
        OrderStatus::Shipped,
        OrderStatus::Delivered,
    ];

    protected function getStats(): array
    {
        $days = (int) ($this->filterFormData['period'] ?? 30);
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        // --- Fatturato ---
        $revenue = Order::whereIn('status', self::PAID_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_price');

        $revenueChart = $this->buildChart($from, $to, $days, function ($start, $end) {
            return (float) Order::whereIn('status', self::PAID_STATUSES)
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_price');
        });

        // --- N° Ordini ---
        $ordersCount = Order::whereBetween('created_at', [$from, $to])->count();

        $ordersChart = $this->buildChart($from, $to, $days, function ($start, $end) {
            return Order::whereBetween('created_at', [$start, $end])->count();
        });

        // --- Valore Medio ---
        $avgOrder = $ordersCount > 0 ? $revenue / $ordersCount : 0;

        // --- Tasso Conversione ---
        $views = ShopEvent::views()->whereBetween('created_at', [$from, $to])->count();
        $purchases = ShopEvent::purchases()->whereBetween('created_at', [$from, $to])->count();
        $conversionRate = $views > 0 ? round(($purchases / $views) * 100, 2) : 0;

        return [
            Stat::make('Fatturato', '€'.number_format($revenue, 2, ',', '.'))
                ->description($days.' giorni')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($revenueChart),

            Stat::make('N° Ordini', number_format($ordersCount))
                ->description($days.' giorni')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->chart($ordersChart),

            Stat::make('Valore Medio', '€'.number_format($avgOrder, 2, ',', '.'))
                ->description('Per ordine')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('warning'),

            Stat::make('Tasso Conversione', $conversionRate.'%')
                ->description($purchases.' acquisti / '.$views.' visite')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($conversionRate > 2 ? 'success' : 'danger'),
        ];
    }

    /**
     * Genera un array di 7 data point suddividendo il periodo in segmenti uguali.
     */
    private function buildChart(Carbon $from, Carbon $to, int $totalDays, callable $queryFn): array
    {
        $segments = 7;
        $segmentDays = max(1, intdiv($totalDays, $segments));
        $chart = [];

        for ($i = 0; $i < $segments; $i++) {
            $segStart = $from->copy()->addDays($i * $segmentDays);
            $segEnd = ($i === $segments - 1)
                ? $to->copy()
                : $segStart->copy()->addDays($segmentDays - 1)->endOfDay();

            $chart[] = $queryFn($segStart, $segEnd);
        }

        return $chart;
    }
}

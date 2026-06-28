<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class OrdersChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Ordini ultimi 6 mesi';

    protected static ?int $sort = 2;

    protected static string $color = 'warning';

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(function ($i) {
            return Carbon::now()->subMonths($i);
        });

        $labels = $months->map(fn ($m) => $m->translatedFormat('M Y'))->toArray();

        $orderCounts = $months->map(function ($month) {
            return Order::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
        })->toArray();

        $revenueCents = $months->map(function ($month) {
            return round((float) Order::where('status', OrderStatus::Paid)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('total_price'), 2);
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Ordini',
                    'data' => $orderCounts,
                    'borderColor' => '#C9A84C',
                    'backgroundColor' => 'rgba(201, 168, 76, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Fatturato (€)',
                    'data' => $revenueCents,
                    'borderColor' => '#003063',
                    'backgroundColor' => 'rgba(0, 48, 99, 0.1)',
                    'fill' => true,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

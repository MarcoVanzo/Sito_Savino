<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrdersByStatusWidget extends ChartWidget
{
    protected static ?string $heading = 'Ordini per Stato';
    protected static ?string $pollingInterval = null;
    protected static ?int $sort = 4;
    protected static bool $isDiscovered = false;
    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $days = (int) ($this->filterFormData['period'] ?? 30);
        $from = now()->subDays($days)->startOfDay();

        $statusColors = [
            OrderStatus::Pending->value => '#f59e0b',
            OrderStatus::Processing->value => '#3b82f6',
            OrderStatus::Paid->value => '#10b981',
            OrderStatus::Shipped->value => '#6366f1',
            OrderStatus::Delivered->value => '#22c55e',
            OrderStatus::Cancelled->value => '#ef4444',
            OrderStatus::Refunded->value => '#8b5cf6',
        ];

        $counts = Order::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $labels = [];
        $data = [];
        $colors = [];

        foreach (OrderStatus::cases() as $status) {
            $count = $counts[$status->value] ?? 0;
            if ($count > 0) {
                $labels[] = $status->getLabel();
                $data[] = $count;
                $colors[] = $statusColors[$status->value];
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}

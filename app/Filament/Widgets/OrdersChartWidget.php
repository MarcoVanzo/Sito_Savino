<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class OrdersChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Ordini ultimi 6 mesi';

    protected static ?int $sort = 2;

    protected static string $color = 'warning';

    // Disabilita il polling automatico
    protected static ?string $pollingInterval = null;

    /**
     * Il widget è auto-discovered e finiva quindi nella dashboard di TUTTI i
     * ruoli con accesso al pannello, esponendo il fatturato mensile a chi
     * OrderPolicy::viewAny() esclude dallo shop (Resp. Comunicazione e Coord.
     * Sportivo). Si allinea al permesso shop.
     */
    public static function canView(): bool
    {
        return auth()->user()?->role?->canManageShop() ?? false;
    }

    protected function getData(): array
    {
        $data = Cache::remember('filament:dashboard:orders_chart', 600, function () {
            $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

            // Una sola query per i conteggi mensili (invece di 6 separate)
            $orderCounts = Order::query()
                ->selectRaw("CONCAT(YEAR(created_at), '-', LPAD(MONTH(created_at), 2, '0')) as ym, COUNT(*) as total")
                ->where('created_at', '>=', $sixMonthsAgo)
                ->groupBy('ym')
                ->pluck('total', 'ym')
                ->toArray();

            // Una sola query per il fatturato mensile (invece di 6 separate)
            $revenueData = Order::query()
                ->selectRaw("CONCAT(YEAR(created_at), '-', LPAD(MONTH(created_at), 2, '0')) as ym, ROUND(SUM(total_price), 2) as revenue")
                ->where('status', OrderStatus::Paid)
                ->where('created_at', '>=', $sixMonthsAgo)
                ->groupBy('ym')
                ->pluck('revenue', 'ym')
                ->toArray();

            // Costruisce gli array con tutti i mesi (anche quelli a zero)
            $months = collect(range(5, 0))->map(fn ($i) => Carbon::now()->subMonths($i));
            $labels = $months->map(fn ($m) => $m->translatedFormat('M Y'))->toArray();

            $counts = $months->map(function ($month) use ($orderCounts) {
                $key = $month->format('Y-m');

                return (int) ($orderCounts[$key] ?? 0);
            })->toArray();

            $revenues = $months->map(function ($month) use ($revenueData) {
                $key = $month->format('Y-m');

                return (float) ($revenueData[$key] ?? 0);
            })->toArray();

            return compact('labels', 'counts', 'revenues');
        });

        return [
            'datasets' => [
                [
                    'label' => 'Ordini',
                    'data' => $data['counts'],
                    'borderColor' => '#C9A84C',
                    'backgroundColor' => 'rgba(201, 168, 76, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Fatturato (€)',
                    'data' => $data['revenues'],
                    'borderColor' => '#003063',
                    'backgroundColor' => 'rgba(0, 48, 99, 0.1)',
                    'fill' => true,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

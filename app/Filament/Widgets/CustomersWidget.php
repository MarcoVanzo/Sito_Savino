<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomersWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 7;

    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $days = (int) ($this->filterFormData['period'] ?? 30);
        $from = now()->subDays($days)->startOfDay();

        // Clienti registrati (user_id distinti) nel periodo
        $registered = Order::whereNotNull('user_id')
            ->where('created_at', '>=', $from)
            ->distinct('user_id')
            ->count('user_id');

        // Clienti guest (guest_email distinte, senza user_id) nel periodo
        $guests = Order::whereNull('user_id')
            ->whereNotNull('guest_email')
            ->where('created_at', '>=', $from)
            ->distinct('guest_email')
            ->count('guest_email');

        $totalCustomers = $registered + $guests;

        // Clienti ricorrenti (user_id con più di 1 ordine nel periodo)
        $recurring = Order::whereNotNull('user_id')
            ->where('created_at', '>=', $from)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        // Ordine medio per cliente registrato nel periodo
        $avgOrdersPerCustomer = $registered > 0
            ? round(Order::whereNotNull('user_id')->where('created_at', '>=', $from)->count() / $registered, 1)
            : 0;

        return [
            Stat::make('Clienti Totali', number_format($totalCustomers))
                ->description($registered.' registrati, '.$guests.' guest')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Clienti Ricorrenti', number_format($recurring))
                ->description('Con più di 1 ordine')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('success'),

            Stat::make('Ordini Medi / Cliente', $avgOrdersPerCustomer)
                ->description('Per cliente registrato')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning'),
        ];
    }
}

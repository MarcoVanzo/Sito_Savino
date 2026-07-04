<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CustomersWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;
    protected static ?int $sort = 7;
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        // Clienti registrati (user_id distinti)
        $registered = Order::whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        // Clienti guest (guest_email distinte, senza user_id)
        $guests = Order::whereNull('user_id')
            ->whereNotNull('guest_email')
            ->distinct('guest_email')
            ->count('guest_email');

        $totalCustomers = $registered + $guests;

        // Clienti ricorrenti (user_id con più di 1 ordine)
        $recurring = Order::whereNotNull('user_id')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        // Ordine medio per cliente registrato
        $avgOrdersPerCustomer = $registered > 0
            ? round(Order::whereNotNull('user_id')->count() / $registered, 1)
            : 0;

        return [
            Stat::make('Clienti Totali', number_format($totalCustomers))
                ->description($registered . ' registrati, ' . $guests . ' guest')
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

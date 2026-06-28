<?php

namespace App\Filament\Widgets;

use App\Models\Game;
use App\Models\Order;
use App\Models\Player;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Atleti Totali', Player::count())
                ->description('Giocatori registrati nel sistema')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
            Stat::make('Partite Totali', Game::count())
                ->description('Partite registrate in calendario')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),
            Stat::make('Ordini E-commerce', Order::count())
                ->description('Totale ordini ricevuti nello shop')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('warning'),
        ];
    }
}

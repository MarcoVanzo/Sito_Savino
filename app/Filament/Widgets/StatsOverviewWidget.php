<?php

namespace App\Filament\Widgets;

use App\Models\Game;
use App\Models\Order;
use App\Models\Player;
use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Atleti Totali', Player::count())
                ->description('Giocatori registrati a roster')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart([7, 10, 15, 20, 25, 25, Player::count()])
                ->color('primary'),
                
            Stat::make('Partite Programmate', Game::where('match_date', '>=', now())->count())
                ->description('In calendario a partire da oggi')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->chart([5, 4, 6, 3, 5, 4, Game::where('match_date', '>=', now())->count()])
                ->color('warning'),
                
            Stat::make('Ordini E-commerce', Order::count())
                ->description('Totale acquisti nello shop')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart([12, 15, 25, 18, 30, 45, Order::count() + 10])
                ->color('success'),

            Stat::make('News Pubblicate', Post::where('status', 'publish')->count())
                ->description('Articoli visibili sul sito')
                ->descriptionIcon('heroicon-m-newspaper')
                ->color('info'),
        ];
    }
}

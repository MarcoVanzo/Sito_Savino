<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Giocatrici a Roster', \App\Models\Player::count())
                ->description('Atlete tesserate')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
                
            Stat::make('Partite Totali', \App\Models\Game::count())
                ->description('In archivio e programmate')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),
                
            Stat::make('News Pubblicate', \App\Models\Post::where('status', 'published')->count())
                ->description('Articoli visibili sul sito')
                ->descriptionIcon('heroicon-m-newspaper')
                ->color('warning'),
        ];
    }
}

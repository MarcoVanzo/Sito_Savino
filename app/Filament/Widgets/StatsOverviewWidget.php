<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Models\Game;
use App\Models\Order;
use App\Models\Player;
use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    // Disabilita il polling automatico — i dati statistici non richiedono aggiornamento in tempo reale
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        // Cache aggregata: una sola lettura da Redis per tutti i conteggi
        $stats = Cache::remember('filament:dashboard:stats', 300, function () {
            return [
                'players' => Player::count(),
                'upcoming_games' => Game::where('match_date', '>=', now())->count(),
                'orders' => Order::count(),
                'published_posts' => Post::where('status', PostStatus::Published)->count(),
            ];
        });

        return [
            Stat::make('Atleti Totali', $stats['players'])
                ->description('Giocatori registrati a roster')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Partite Programmate', $stats['upcoming_games'])
                ->description('In calendario a partire da oggi')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('Ordini E-commerce', $stats['orders'])
                ->description('Totale acquisti nello shop')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('success'),

            Stat::make('News Pubblicate', $stats['published_posts'])
                ->description('Articoli visibili sul sito')
                ->descriptionIcon('heroicon-m-newspaper')
                ->color('info'),
        ];
    }
}

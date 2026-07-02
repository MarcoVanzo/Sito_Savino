<?php

namespace App\Filament\Widgets;

use App\Models\Game;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class NextMatchWidget extends Widget
{
    protected static string $view = 'filament.widgets.next-match-widget';

    protected static ?int $sort = 3;

    // Disabilita il polling automatico
    protected static ?string $pollingInterval = null;

    protected function getViewData(): array
    {
        $nextMatch = Cache::remember('filament:dashboard:next_match', 1800, function () {
            return Game::with(['homeTeam', 'awayTeam'])
                ->where('match_date', '>=', now())
                ->orderBy('match_date', 'asc')
                ->first();
        });

        return [
            'nextMatch' => $nextMatch,
        ];
    }
}

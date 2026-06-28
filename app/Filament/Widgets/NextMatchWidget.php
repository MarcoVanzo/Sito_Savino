<?php

namespace App\Filament\Widgets;

use App\Models\Game;
use Filament\Widgets\Widget;

class NextMatchWidget extends Widget
{
    protected static string $view = 'filament.widgets.next-match-widget';
    protected static ?int $sort = 3;

    protected function getViewData(): array
    {
        $nextMatch = Game::with(['homeTeam', 'awayTeam'])
            ->where('match_date', '>=', now())
            ->orderBy('match_date', 'asc')
            ->first();

        return [
            'nextMatch' => $nextMatch,
        ];
    }
}

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
        // In cache va solo l'id: serializzare il modello Eloquent (con relazioni e
        // media) nella cache su database produce un __PHP_Incomplete_Class alla
        // rilettura e manda in 500 tutta la dashboard.
        $nextMatchId = Cache::remember('filament:dashboard:next_match_id', 1800, function () {
            // La prossima gara della società: il calendario contiene l'intero
            // campionato, e la prima gara futura era quasi sempre di altre due squadre.
            return Game::query()
                ->where('match_date', '>=', now())
                ->where(fn ($gare) => $gare
                    ->whereHas('homeTeam', fn ($squadra) => $squadra->where('is_internal', true))
                    ->orWhereHas('awayTeam', fn ($squadra) => $squadra->where('is_internal', true)))
                ->orderBy('match_date', 'asc')
                ->value('id');
        });

        $nextMatch = $nextMatchId
            ? Game::with(['homeTeam', 'awayTeam'])->find($nextMatchId)
            : null;

        return [
            'nextMatch' => $nextMatch,
        ];
    }
}

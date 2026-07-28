<?php

namespace App\Filament\Resources\StandingResource\Pages;

use App\Filament\Resources\StandingResource;
use App\Models\Standing;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListStandings extends ListRecords
{
    protected static string $resource = StandingResource::class;

    /**
     * Nessuna azione di testata: la classifica non si crea a mano.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Spiega perché mancano i pulsanti di modifica: senza questa riga
     * l'assenza del tasto "Nuovo" verrebbe letta come un malfunzionamento.
     */
    public function getSubheading(): ?string
    {
        $lastSync = Standing::query()->max('synced_at');

        $suffix = $lastSync !== null
            ? ' Ultima sincronizzazione: '.Carbon::parse($lastSync)->format('d/m/Y H:i').'.'
            : ' Nessuna sincronizzazione ancora eseguita.';

        return 'Dati importati dal sito della Lega Volley Femminile e riscritti a ogni '
            .'sincronizzazione: sono di sola lettura, una modifica manuale andrebbe persa.'
            .$suffix;
    }
}

<?php

namespace App\Filament\Resources\PlayerStatResource\Pages;

use App\Filament\Resources\PlayerStatResource;
use App\Models\PlayerStat;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListPlayerStats extends ListRecords
{
    protected static string $resource = PlayerStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Resta disponibile: le giovanili non hanno tabellini della Lega e
            // i loro totali si inseriscono qui.
            Actions\CreateAction::make()
                ->label('Nuova riga manuale'),
        ];
    }

    /**
     * Spiega perché su molte righe manca il pulsante di modifica: senza questa
     * riga l'assenza verrebbe letta come un malfunzionamento.
     */
    public function getSubheading(): ?string
    {
        $lastSync = PlayerStat::query()->max('last_synced_at');

        $suffix = $lastSync !== null
            ? ' Ultima sincronizzazione: '.Carbon::parse($lastSync)->format('d/m/Y H:i').'.'
            : ' Nessuna sincronizzazione ancora eseguita.';

        return 'Totali ricostruiti dai tabellini della Lega a ogni sincronizzazione: quelle righe '
            .'sono di sola lettura. Si creano e si modificano solo le righe inserite a mano, usate '
            .'per le squadre giovanili, i cui campionati non hanno tabellini pubblicati.'
            .$suffix;
    }
}

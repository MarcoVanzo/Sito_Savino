<?php

namespace App\Filament\Resources\PlayerStatResource\Pages;

use App\Filament\Resources\PlayerStatResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/**
 * Scheda con TUTTI i numeri della riga: in tabella la maggior parte delle
 * colonne è nascosta per leggibilità, qui non si nasconde niente.
 */
class ViewPlayerStat extends ViewRecord
{
    protected static string $resource = PlayerStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // EditAction si nasconde da sola sulle righe importate:
            // PlayerStatResource::canEdit() le esclude.
            Actions\EditAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        return PlayerStatResource::isManual($this->getRecord())
            ? 'Riga inserita dal CMS: la sincronizzazione con la Lega non la modifica.'
            : 'Riga ricostruita dai tabellini della Lega a ogni sincronizzazione: è di sola '
                .'lettura, una modifica manuale andrebbe persa al giro successivo.';
    }
}

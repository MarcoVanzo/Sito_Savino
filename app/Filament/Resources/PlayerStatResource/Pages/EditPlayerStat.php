<?php

namespace App\Filament\Resources\PlayerStatResource\Pages;

use App\Filament\Resources\PlayerStatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Raggiungibile solo per le righe inserite a mano: PlayerStatResource::canEdit()
 * esclude quelle importate, e Filament autorizza anche l'accesso diretto all'URL
 * con lo stesso metodo.
 */
class EditPlayerStat extends EditRecord
{
    protected static string $resource = PlayerStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Riga inserita dal CMS: la sincronizzazione con la Lega non la sovrascrive, '
            .'perché ricostruisce solo i totali delle gare che hanno un tabellino.';
    }
}

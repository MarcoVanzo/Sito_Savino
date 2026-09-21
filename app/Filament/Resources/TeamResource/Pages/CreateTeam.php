<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    /**
     * Una squadra creata a mano è una squadra della società: le avversarie
     * arrivano solo dalla sincronizzazione con la Lega. Senza `is_internal` la
     * squadra risultava "avversaria importata" e al primo sync rischiava di
     * essere duplicata, spezzando il legame con rose e statistiche.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_internal'] = true;

        return $data;
    }
}

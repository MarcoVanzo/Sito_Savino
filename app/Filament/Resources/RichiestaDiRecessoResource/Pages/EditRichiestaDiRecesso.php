<?php

namespace App\Filament\Resources\RichiestaDiRecessoResource\Pages;

use App\Filament\Resources\RichiestaDiRecessoResource;
use Filament\Resources\Pages\EditRecord;

class EditRichiestaDiRecesso extends EditRecord
{
    protected static string $resource = RichiestaDiRecessoResource::class;

    /**
     * I campi della dichiarazione sono disabilitati nel modulo: qui si scrive
     * solo la gestione, anche se qualcuno forzasse lo stato di Livewire.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return array_intersect_key($data, array_flip(['gestita_il', 'note_interne']));
    }
}

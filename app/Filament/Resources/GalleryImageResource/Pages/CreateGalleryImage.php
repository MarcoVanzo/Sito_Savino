<?php

namespace App\Filament\Resources\GalleryImageResource\Pages;

use App\Filament\Resources\GalleryImageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGalleryImage extends CreateRecord
{
    protected static string $resource = GalleryImageResource::class;

    /**
     * Sincronizza le relazioni polimorfiche dopo la creazione del record.
     */
    protected function afterCreate(): void
    {
        $data = $this->data;

        $this->record->players()->sync($data['players'] ?? []);
        $this->record->staffMembers()->sync($data['staff_members'] ?? []);
    }
}

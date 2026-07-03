<?php

namespace App\Filament\Resources\GalleryImageResource\Pages;

use App\Filament\Resources\GalleryImageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGalleryImage extends EditRecord
{
    protected static string $resource = GalleryImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Sincronizza le relazioni polimorfiche dopo il salvataggio del record.
     * I select nel form usano ->options() manuale perché Filament non supporta
     * ->relationship() con morphedByMany, quindi gestiamo il sync manualmente.
     */
    protected function afterSave(): void
    {
        $data = $this->data;

        $this->record->players()->sync($data['players'] ?? []);
        $this->record->staffMembers()->sync($data['staff_members'] ?? []);
    }
}

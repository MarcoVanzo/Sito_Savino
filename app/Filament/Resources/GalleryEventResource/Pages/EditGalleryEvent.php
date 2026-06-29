<?php

namespace App\Filament\Resources\GalleryEventResource\Pages;

use App\Filament\Resources\GalleryEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGalleryEvent extends EditRecord
{
    protected static string $resource = GalleryEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

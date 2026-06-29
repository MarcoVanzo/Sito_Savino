<?php

namespace App\Filament\Resources\GalleryEventResource\Pages;

use App\Filament\Resources\GalleryEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGalleryEvents extends ListRecords
{
    protected static string $resource = GalleryEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

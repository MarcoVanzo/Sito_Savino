<?php

namespace App\Filament\Resources\GalleryEventResource\Pages;

use App\Filament\Resources\GalleryEventResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateGalleryEvent extends CreateRecord
{
    use Translatable;

    protected static string $resource = GalleryEventResource::class;
}

<?php

namespace App\Filament\Resources\YouthStaffResource\Pages;

use App\Filament\Resources\YouthStaffResource;
use App\Filament\Traits\InvalidaLaCacheDopoIlRiordino;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListYouthStaff extends ListRecords
{
    use InvalidaLaCacheDopoIlRiordino;
    use Translatable;

    protected static string $resource = YouthStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

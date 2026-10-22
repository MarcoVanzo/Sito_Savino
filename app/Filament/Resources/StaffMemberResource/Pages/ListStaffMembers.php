<?php

namespace App\Filament\Resources\StaffMemberResource\Pages;

use App\Filament\Resources\StaffMemberResource;
use App\Filament\Traits\InvalidaLaCacheDopoIlRiordino;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListStaffMembers extends ListRecords
{
    use InvalidaLaCacheDopoIlRiordino;
    use Translatable;

    protected static string $resource = StaffMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

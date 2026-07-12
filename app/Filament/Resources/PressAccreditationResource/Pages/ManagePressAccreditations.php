<?php

namespace App\Filament\Resources\PressAccreditationResource\Pages;

use App\Filament\Resources\PressAccreditationResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePressAccreditations extends ManageRecords
{
    protected static string $resource = PressAccreditationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

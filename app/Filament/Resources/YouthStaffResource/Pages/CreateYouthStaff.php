<?php

namespace App\Filament\Resources\YouthStaffResource\Pages;

use App\Filament\Resources\YouthStaffResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateYouthStaff extends CreateRecord
{
    use Translatable;

    protected static string $resource = YouthStaffResource::class;
}

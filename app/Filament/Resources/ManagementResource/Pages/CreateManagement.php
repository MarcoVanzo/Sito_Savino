<?php

namespace App\Filament\Resources\ManagementResource\Pages;

use App\Filament\Resources\ManagementResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateManagement extends CreateRecord
{
    use Translatable;

    protected static string $resource = ManagementResource::class;
}

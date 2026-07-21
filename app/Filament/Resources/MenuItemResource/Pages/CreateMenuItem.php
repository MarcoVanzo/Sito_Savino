<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateMenuItem extends CreateRecord
{
    use Translatable;

    protected static string $resource = MenuItemResource::class;
}

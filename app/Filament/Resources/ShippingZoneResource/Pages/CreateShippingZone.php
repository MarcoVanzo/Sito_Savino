<?php

namespace App\Filament\Resources\ShippingZoneResource\Pages;

use App\Filament\Resources\ShippingZoneResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateShippingZone extends CreateRecord
{
    use Translatable;

    protected static string $resource = ShippingZoneResource::class;
}

<?php

namespace App\Filament\Resources\SponsorResource\Pages;

use App\Filament\Resources\SponsorResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateSponsor extends CreateRecord
{
    use Translatable;

protected static string $resource = SponsorResource::class;
}

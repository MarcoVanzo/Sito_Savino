<?php

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Resources\HeroSlideResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateHeroSlide extends CreateRecord
{
    use Translatable;

protected static string $resource = HeroSlideResource::class;
}

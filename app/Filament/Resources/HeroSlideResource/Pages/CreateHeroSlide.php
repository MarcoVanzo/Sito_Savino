<?php

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Pages\Settings\HomepageSettingsPage;
use App\Filament\Resources\HeroSlideResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateHeroSlide extends CreateRecord
{
    use Translatable;

    protected static string $resource = HeroSlideResource::class;

    protected function getRedirectUrl(): string
    {
        return HomepageSettingsPage::getUrl(['tab' => 'slides']);
    }
}

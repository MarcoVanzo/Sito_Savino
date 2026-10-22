<?php

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Pages\Settings\HomepageSettingsPage;
use App\Filament\Resources\HeroSlideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditHeroSlide extends EditRecord
{
    use Translatable;

    protected static string $resource = HeroSlideResource::class;

    protected function getRedirectUrl(): string
    {
        return HomepageSettingsPage::getUrl(['tab' => 'slides']);
    }

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}

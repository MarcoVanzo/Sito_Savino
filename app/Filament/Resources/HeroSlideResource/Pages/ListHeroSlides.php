<?php

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Pages\Settings\HomepageSettingsPage;
use App\Filament\Resources\HeroSlideResource;
use App\Filament\Traits\InvalidaLaCacheDopoIlRiordino;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListHeroSlides extends ListRecords
{
    use InvalidaLaCacheDopoIlRiordino;
    use Translatable;

    protected static string $resource = HeroSlideResource::class;

    public function mount(): void
    {
        redirect(HomepageSettingsPage::getUrl(['tab' => 'slides']));
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

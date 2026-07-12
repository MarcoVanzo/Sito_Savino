<?php

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Pages\Settings\HomepageSettingsPage;
use App\Filament\Resources\HeroSlideResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListHeroSlides extends ListRecords
{
    use Translatable;

    protected static string $resource = HeroSlideResource::class;

    public function mount(): void
    {
        redirect(HomepageSettingsPage::getUrl(['tab' => 'slides']));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

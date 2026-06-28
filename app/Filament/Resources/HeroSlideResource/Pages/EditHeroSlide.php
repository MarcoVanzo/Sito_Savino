<?php

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Resources\HeroSlideResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditHeroSlide extends EditRecord
{
    use Translatable;

protected static string $resource = HeroSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

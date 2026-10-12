<?php

namespace App\Filament\Resources\LiveCreativeResource\Pages;

use App\Filament\Resources\LiveCreativeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateLiveCreative extends CreateRecord
{
    use Translatable;

    protected static string $resource = LiveCreativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return LiveCreativeResource::getUrl('index');
    }
}

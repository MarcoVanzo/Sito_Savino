<?php

namespace App\Filament\Resources\LiveCreativeResource\Pages;

use App\Filament\Resources\LiveCreativeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditLiveCreative extends EditRecord
{
    use Translatable;

    protected static string $resource = LiveCreativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return LiveCreativeResource::getUrl('index');
    }
}

<?php

namespace App\Filament\Resources\EventoResource\Pages;

use App\Filament\Resources\EventoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateEvento extends CreateRecord
{
    use Translatable;

    protected static string $resource = EventoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return EventoResource::getUrl('index');
    }
}

<?php

namespace App\Filament\Resources\RosterResource\Pages;

use App\Filament\Resources\RosterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoster extends EditRecord
{
    protected static string $resource = RosterResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

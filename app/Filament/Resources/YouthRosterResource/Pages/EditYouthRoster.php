<?php

namespace App\Filament\Resources\YouthRosterResource\Pages;

use App\Filament\Resources\YouthRosterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditYouthRoster extends EditRecord
{
    protected static string $resource = YouthRosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

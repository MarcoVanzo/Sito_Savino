<?php

namespace App\Filament\Resources\YouthRosterResource\Pages;

use App\Filament\Resources\YouthRosterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListYouthRosters extends ListRecords
{
    protected static string $resource = YouthRosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

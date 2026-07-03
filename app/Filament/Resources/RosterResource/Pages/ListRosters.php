<?php

namespace App\Filament\Resources\RosterResource\Pages;

use App\Filament\Resources\RosterResource;
use App\Filament\Traits\HasFaceSyncMethods;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRosters extends ListRecords
{
    use HasFaceSyncMethods;

    protected static string $resource = RosterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

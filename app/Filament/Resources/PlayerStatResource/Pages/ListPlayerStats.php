<?php

namespace App\Filament\Resources\PlayerStatResource\Pages;

use App\Filament\Resources\PlayerStatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlayerStats extends ListRecords
{
    protected static string $resource = PlayerStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

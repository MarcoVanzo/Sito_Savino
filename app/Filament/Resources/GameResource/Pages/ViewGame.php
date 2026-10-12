<?php

namespace App\Filament\Resources\GameResource\Pages;

use App\Filament\Actions\DirettaESponsorAction;
use App\Filament\Resources\GameResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGame extends ViewRecord
{
    protected static string $resource = GameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DirettaESponsorAction::make(),
            Actions\EditAction::make(),
        ];
    }
}

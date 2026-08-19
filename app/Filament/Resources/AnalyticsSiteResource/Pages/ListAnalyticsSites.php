<?php

namespace App\Filament\Resources\AnalyticsSiteResource\Pages;

use App\Filament\Resources\AnalyticsSiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnalyticsSites extends ListRecords
{
    protected static string $resource = AnalyticsSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

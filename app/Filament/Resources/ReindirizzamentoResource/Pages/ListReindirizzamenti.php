<?php

namespace App\Filament\Resources\ReindirizzamentoResource\Pages;

use App\Filament\Resources\ReindirizzamentoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReindirizzamenti extends ListRecords
{
    protected static string $resource = ReindirizzamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

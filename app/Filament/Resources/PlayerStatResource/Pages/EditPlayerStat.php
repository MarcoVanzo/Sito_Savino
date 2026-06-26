<?php

namespace App\Filament\Resources\PlayerStatResource\Pages;

use App\Filament\Resources\PlayerStatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlayerStat extends EditRecord
{
    protected static string $resource = PlayerStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

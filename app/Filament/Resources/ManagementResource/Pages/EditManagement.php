<?php

namespace App\Filament\Resources\ManagementResource\Pages;

use App\Filament\Resources\ManagementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditManagement extends EditRecord
{
    use Translatable;

    protected static string $resource = ManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}

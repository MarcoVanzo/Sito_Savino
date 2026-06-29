<?php

namespace App\Filament\Resources\YouthStaffResource\Pages;

use App\Filament\Resources\YouthStaffResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditYouthStaff extends EditRecord
{
    use Translatable;

    protected static string $resource = YouthStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

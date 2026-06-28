<?php

namespace App\Filament\Resources\StaffMemberResource\Pages;

use App\Filament\Resources\StaffMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditStaffMember extends EditRecord
{
    use Translatable;

protected static string $resource = StaffMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

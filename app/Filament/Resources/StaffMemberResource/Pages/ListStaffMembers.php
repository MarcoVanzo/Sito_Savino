<?php

namespace App\Filament\Resources\StaffMemberResource\Pages;

use App\Filament\Resources\StaffMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListStaffMembers extends ListRecords
{
    use Translatable;

protected static string $resource = StaffMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

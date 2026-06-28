<?php

namespace App\Filament\Resources\StaffMemberResource\Pages;

use App\Filament\Resources\StaffMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateStaffMember extends CreateRecord
{
    use Translatable;

protected static string $resource = StaffMemberResource::class;
}

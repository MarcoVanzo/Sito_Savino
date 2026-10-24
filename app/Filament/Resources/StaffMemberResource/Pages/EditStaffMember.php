<?php

namespace App\Filament\Resources\StaffMemberResource\Pages;

use App\Filament\Actions\RevocaRiconoscimentoVoltiAction;
use App\Filament\Resources\StaffMemberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditStaffMember extends EditRecord
{
    use Translatable;

    protected static string $resource = StaffMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            RevocaRiconoscimentoVoltiAction::perLaScheda(),
            DeleteAction::make(),
        ];
    }
}

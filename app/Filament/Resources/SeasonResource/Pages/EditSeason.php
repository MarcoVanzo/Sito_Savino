<?php

namespace App\Filament\Resources\SeasonResource\Pages;

use App\Filament\Resources\SeasonResource;
use App\Models\Season;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeason extends EditRecord
{
    protected static string $resource = SeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Una sola stagione può essere "corrente": le altre vengono disattivate
     * solo dopo che il salvataggio è andato a buon fine.
     */
    protected function afterSave(): void
    {
        $season = $this->getRecord();

        if (! $season instanceof Season || ! $season->is_current) {
            return;
        }

        Season::where('is_current', true)
            ->whereKeyNot($season->getKey())
            ->update(['is_current' => false]);
    }
}

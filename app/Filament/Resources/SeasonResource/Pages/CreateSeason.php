<?php

namespace App\Filament\Resources\SeasonResource\Pages;

use App\Filament\Resources\SeasonResource;
use App\Models\Season;
use Filament\Resources\Pages\CreateRecord;

class CreateSeason extends CreateRecord
{
    protected static string $resource = SeasonResource::class;

    /**
     * Una sola stagione può essere "corrente": le altre vengono disattivate
     * solo dopo che il salvataggio è andato a buon fine.
     */
    protected function afterCreate(): void
    {
        $season = $this->record;

        if (! $season instanceof Season || ! $season->is_current) {
            return;
        }

        Season::where('is_current', true)
            ->whereKeyNot($season->getKey())
            ->update(['is_current' => false]);
    }
}

<?php

namespace App\Filament\Actions;

use App\Models\Roster;
use Filament\Tables\Actions\Action;

class SyncFaceAction
{
    public static function make(string $name = 'syncFace'): Action
    {
        return Action::make($name)
            ->label('Addestra AI (Sincronizza Volto)')
            ->icon('heroicon-o-face-smile')
            ->color('info')
            ->modalHeading('Addestramento AI')
            ->modalContent(fn (Roster $record) => view('filament.actions.sync-face-progress', [
                'recordId' => $record->getKey(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->closeModalByClickingAway(false);
    }
}

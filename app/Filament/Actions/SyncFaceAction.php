<?php

namespace App\Filament\Actions;

use App\Models\Roster;
use App\Services\FacialRecognitionService;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class SyncFaceAction
{
    public static function make(string $name = 'syncFace'): Action
    {
        return Action::make($name)
            ->label('Addestra AI (Sincronizza Volto)')
            ->icon('heroicon-o-face-smile')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Sincronizza Volto con AI')
            ->modalDescription('Invia la foto ufficiale di questa atleta al sistema di riconoscimento facciale per addestrare l\'AI a riconoscerla.')
            ->action(function (Roster $record) {
                $media = $record->getFirstMedia('rosters_official') ?? $record->player->getFirstMedia('players');
                if (! $media) {
                    Notification::make()
                        ->title('Errore')
                        ->body('Nessuna foto trovata (né ufficiale né avatar) per addestrare l\'AI.')
                        ->danger()
                        ->send();

                    return;
                }

                $service = app(FacialRecognitionService::class);
                $success = $service->addFaceExampleFromMedia($record->player, $media);

                if ($success) {
                    Notification::make()
                        ->title('Successo')
                        ->body('Volto sincronizzato! L\'AI ora riconoscerà questa atleta.')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Errore API')
                        ->body('Impossibile sincronizzare il volto. Verifica i log.')
                        ->danger()
                        ->send();
                }
            });
    }
}

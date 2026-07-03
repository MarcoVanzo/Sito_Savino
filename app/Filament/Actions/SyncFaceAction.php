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
            ->modalDescription('Invia tutte le foto (ufficiale + in azione) al sistema di riconoscimento facciale per addestrare l\'AI a riconoscere questa atleta.')
            ->action(function (Roster $record) {
                // Raccogli TUTTE le foto: ufficiale + azione + avatar player
                $allMedia = collect();

                $officialPhoto = $record->getFirstMedia('rosters_official');
                if ($officialPhoto) {
                    $allMedia->push($officialPhoto);
                }

                $actionPhotos = $record->getMedia('rosters_action');
                if ($actionPhotos->isNotEmpty()) {
                    $allMedia = $allMedia->merge($actionPhotos);
                }

                // Fallback: avatar del player se non ci sono foto roster
                if ($allMedia->isEmpty()) {
                    $playerAvatar = $record->player->getFirstMedia('players');
                    if ($playerAvatar) {
                        $allMedia->push($playerAvatar);
                    }
                }

                if ($allMedia->isEmpty()) {
                    Notification::make()
                        ->title('Errore')
                        ->body('Nessuna foto trovata (né ufficiale, né in azione, né avatar) per addestrare l\'AI.')
                        ->danger()
                        ->send();

                    return;
                }

                $service = app(FacialRecognitionService::class);
                $successCount = 0;
                $failCount = 0;

                foreach ($allMedia as $media) {
                    $result = $service->addFaceExampleFromMedia($record->player, $media);
                    if (is_array($result) ? $result['success'] : $result) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                }

                // Aggiorna il contatore con il totale reale
                $record->player->update(['ai_face_examples' => $record->player->ai_face_examples + $successCount]);

                if ($successCount > 0 && $failCount === 0) {
                    Notification::make()
                        ->title('Successo')
                        ->body("Tutte le {$successCount} foto sincronizzate! L'AI ora riconoscerà meglio questa atleta.")
                        ->success()
                        ->send();
                } elseif ($successCount > 0) {
                    Notification::make()
                        ->title('Parzialmente completato')
                        ->body("{$successCount} foto sincronizzate, {$failCount} fallite. Verifica i log.")
                        ->warning()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Errore API')
                        ->body('Nessuna foto sincronizzata. Verifica i log.')
                        ->danger()
                        ->send();
                }
            });
    }
}

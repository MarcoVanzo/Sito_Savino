<?php

namespace App\Filament\Resources\PlayerResource\Pages;

use App\Filament\Actions\TrainAiFacesAction;
use App\Filament\Resources\PlayerResource;
use App\Services\FacialRecognitionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditPlayer extends EditRecord
{
    use Translatable;

    protected static string $resource = PlayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('addTrainingFaces')
                    ->label('Addestra AI (Upload Foto)')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->form(TrainAiFacesAction::formSchema())
                    ->modalHeading('Addestra Intelligenza Artificiale')
                    ->modalDescription('L\'AI imparerà a riconoscere questa atleta analizzando le foto caricate. Questo processo non cancellerà le foto precedenti.')
                    ->modalSubmitActionLabel('Invia e Addestra')
                    ->action(function (array $data) {
                        TrainAiFacesAction::execute($this->record, $data);
                    }),

                Actions\Action::make('resetAiFaces')
                    ->label('Resetta Memoria Volto')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Resetta Volti da CompreFace')
                    ->modalDescription('Attenzione: L\'AI dimenticherà completamente come riconoscere questa atleta. Dovrai addestrarla di nuovo. Procedere?')
                    ->action(function () {
                        $service = app(FacialRecognitionService::class);
                        if ($service->deleteAllSubjectExamples($this->record)) {
                            Notification::make()
                                ->title('Memoria Azzerata')
                                ->body('L\'AI non riconoscerà più questa atleta fino al prossimo addestramento.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Errore API')
                                ->body('Impossibile azzerare la memoria.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])->icon('heroicon-m-sparkles')->label('Azioni AI'),
            Actions\DeleteAction::make(),
        ];
    }
}

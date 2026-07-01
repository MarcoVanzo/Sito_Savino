<?php

namespace App\Filament\Resources\PlayerResource\Pages;

use App\Filament\Resources\PlayerResource;
use Filament\Actions;
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
                    ->form([
                        \Filament\Forms\Components\FileUpload::make('training_images')
                            ->label('Foto per l\'Addestramento')
                            ->multiple()
                            ->image()
                            ->helperText('Carica più foto del volto da diverse angolazioni. Non verranno salvate sul server, ma solo inviate all\'AI.')
                            ->required(),
                    ])
                    ->modalHeading('Addestra Intelligenza Artificiale')
                    ->modalDescription('L\'AI imparerà a riconoscere questa atleta analizzando le foto caricate. Questo processo non cancellerà le foto precedenti.')
                    ->modalSubmitActionLabel('Invia e Addestra')
                    ->action(function (array $data) {
                        $record = $this->record;
                        $service = app(\App\Services\FacialRecognitionService::class);
                        $successCount = 0;
                        $errorCount = 0;

                        // Ensure subject exists
                        $service->createSubject($record);

                        if (!empty($data['training_images'])) {
                            foreach ($data['training_images'] as $image) {
                                $path = is_string($image) ? storage_path('app/public/' . $image) : $image->getRealPath();
                                
                                if ($service->addFaceExample($record, $path)) {
                                    $successCount++;
                                } else {
                                    $errorCount++;
                                }
                            }
                        }

                        // Sync avatar as well
                        $media = $record->getFirstMedia('players');
                        if ($media && $service->addFaceExampleFromMedia($record, $media)) {
                            $successCount++;
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Addestramento Completato')
                            ->body("{$successCount} volti appresi con successo. " . ($errorCount > 0 ? "{$errorCount} errori." : ""))
                            ->status($errorCount > 0 ? 'warning' : 'success')
                            ->send();
                    }),

                Actions\Action::make('resetAiFaces')
                    ->label('Resetta Memoria Volto')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Resetta Volti da CompreFace')
                    ->modalDescription('Attenzione: L\'AI dimenticherà completamente come riconoscere questa atleta. Dovrai addestrarla di nuovo. Procedere?')
                    ->action(function () {
                        $record = $this->record;
                        $service = app(\App\Services\FacialRecognitionService::class);
                        if ($service->deleteAllSubjectExamples($record)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Memoria Azzerata')
                                ->body('L\'AI non riconoscerà più questa atleta fino al prossimo addestramento.')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
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

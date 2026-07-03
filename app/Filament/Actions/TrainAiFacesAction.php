<?php

namespace App\Filament\Actions;

use App\Services\FacialRecognitionService;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TrainAiFacesAction
{
    /**
     * Execute the AI training action for a person (Player or StaffMember).
     * Handles uploaded training images and syncs avatar.
     */
    public static function execute(Model $record, array $data): void
    {
        $service = app(FacialRecognitionService::class);
        $successCount = 0;
        $errorCount = 0;

        // Ensure subject exists in CompreFace
        $service->createSubject($record);

        $errorMessages = [];

        if (! empty($data['training_images'])) {
            foreach ($data['training_images'] as $image) {
                $path = is_string($image)
                    ? Storage::disk('local')->path($image)
                    : $image->getRealPath();

                $result = $service->addFaceExample($record, $path);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                    if ($result['error']) {
                        $errorMessages[] = $result['error'];
                    }
                }
            }
        }

        // Sync avatar as well — determina automaticamente la collection in base al tipo di modello
        $mediaCollection = static::getMediaCollection($record);
        $media = $record->getFirstMedia($mediaCollection);
        if ($media) {
            $result = $service->addFaceExampleFromMedia($record, $media);
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
                if ($result['error']) {
                    $errorMessages[] = $result['error'];
                }
            }
        }

        $body = "{$successCount} volti appresi con successo.".($errorCount > 0 ? " {$errorCount} scartati." : '');

        if ($errorCount > 0) {
            // Deduplicate and translate common errors
            $uniqueErrors = array_unique($errorMessages);
            $translatedErrors = array_map(function ($err) {
                if (str_contains(strtolower($err), 'more than one face')) {
                    return 'Trovati più volti nella foto (usa un primo piano).';
                }
                if (str_contains(strtolower($err), 'no face is found')) {
                    return 'Nessun volto trovato nella foto.';
                }

                return $err;
            }, $uniqueErrors);

            if (count($translatedErrors) > 0) {
                $body .= ' Motivi: '.implode(' | ', $translatedErrors);
            }

            Notification::make()
                ->title('Addestramento Completato')
                ->body($body)
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Addestramento Completato')
                ->body($body)
                ->success()
                ->send();
        }
    }

    /**
     * Get the form schema for the training upload modal.
     */
    public static function formSchema(): array
    {
        return [
            FileUpload::make('training_images')
                ->label('Foto per l\'Addestramento')
                ->multiple()
                ->image()
                ->disk('local')
                ->directory('temp_ai_training')
                ->helperText('Carica più foto del volto da diverse angolazioni. Non verranno salvate sul server, ma solo inviate all\'AI.')
                ->required(),
        ];
    }

    /**
     * Determina la media collection in base al tipo di modello.
     */
    protected static function getMediaCollection(Model $record): string
    {
        return match (get_class($record)) {
            \App\Models\Player::class => 'players',
            \App\Models\StaffMember::class => 'staff',
            default => 'default',
        };
    }
}

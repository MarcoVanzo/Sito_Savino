<?php

namespace App\Filament\Actions;

use App\Models\Player;
use App\Services\FacialRecognitionService;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class TrainAiFacesAction
{
    /**
     * Execute the AI training action for a player.
     * Handles uploaded training images and syncs avatar.
     */
    public static function execute(Player $record, array $data): void
    {
        $service = app(FacialRecognitionService::class);
        $successCount = 0;
        $errorCount = 0;

        // Ensure subject exists in CompreFace
        $service->createSubject($record);

        if (! empty($data['training_images'])) {
            foreach ($data['training_images'] as $image) {
                $path = is_string($image)
                    ? Storage::disk('local')->path($image)
                    : $image->getRealPath();

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

        $notification = Notification::make()
            ->title('Addestramento Completato')
            ->body("{$successCount} volti appresi con successo." . ($errorCount > 0 ? " {$errorCount} errori." : ''));

        if ($errorCount > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
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
}

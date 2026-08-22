<?php

namespace App\Filament\Actions;

use App\Models\Player;
use App\Models\StaffMember;
use App\Services\FacialRecognitionService;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
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

        // Ensure subject exists in CompreFace
        $service->createSubject($record);

        $esiti = [];

        foreach ($data['training_images'] ?? [] as $image) {
            $esiti[] = self::apprendiDaFile($service, $record, $image);
        }

        // Sync avatar as well — la collection dipende dal tipo di modello
        $media = $record->getFirstMedia(static::getMediaCollection($record));

        if ($media) {
            $esiti[] = $service->addFaceExampleFromMedia($record, $media);
        }

        $appresi = count(array_filter($esiti, fn (array $esito) => $esito['success']));

        self::notifica($appresi, count($esiti) - $appresi, self::motiviLeggibili($esiti));
    }

    /**
     * Manda una foto all'AI e ripulisce il file temporaneo appena inviato: le
     * immagini caricate per l'addestramento non restano sul server.
     *
     * @param  string|UploadedFile  $image
     * @return array<string, mixed>
     */
    private static function apprendiDaFile(FacialRecognitionService $service, Model $record, $image): array
    {
        $path = is_string($image)
            ? Storage::disk('local')->path($image)
            : $image->getRealPath();

        $esito = $service->addFaceExample($record, $path);

        if (is_string($image)) {
            Storage::disk('local')->delete($image);
        }

        return $esito;
    }

    /**
     * Motivi degli scarti, senza doppioni e nella lingua del pannello.
     *
     * @param  array<int, array<string, mixed>>  $esiti
     * @return array<int, string>
     */
    private static function motiviLeggibili(array $esiti): array
    {
        $messaggi = array_unique(array_filter(array_column($esiti, 'error')));

        return array_map(function (string $errore): string {
            $minuscolo = strtolower($errore);

            if (str_contains($minuscolo, 'more than one face')) {
                return 'Trovati più volti nella foto (usa un primo piano).';
            }

            if (str_contains($minuscolo, 'no face is found')) {
                return 'Nessun volto trovato nella foto.';
            }

            return $errore;
        }, $messaggi);
    }

    /**
     * @param  array<int, string>  $motivi
     */
    private static function notifica(int $appresi, int $scartati, array $motivi): void
    {
        $body = "{$appresi} volti appresi con successo.".($scartati > 0 ? " {$scartati} scartati." : '');

        if ($motivi !== []) {
            $body .= ' Motivi: '.implode(' | ', $motivi);
        }

        $notifica = Notification::make()
            ->title('Addestramento Completato')
            ->body($body);

        ($scartati > 0 ? $notifica->warning() : $notifica->success())->send();
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
            Player::class => 'players',
            StaffMember::class => 'staff',
            default => 'default',
        };
    }
}

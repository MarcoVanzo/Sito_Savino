<?php

namespace App\Filament\Support;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Throwable;

/**
 * Un file caricato nel pannello e salvato quando non c'è più.
 *
 * Livewire tiene i caricamenti in `livewire-tmp/` sul disco locale del
 * container finché non si preme Salva. Un rilascio sostituisce il container, e
 * con lui quei file; lo stesso fa la pulizia di Livewire dopo 24 ore. Il Salva
 * successivo andava in 500 su Sentry (`UnableToRetrieveMetadata`, 05/10 alle
 * 11:18 sulla pagina 26, file caricato prima del rilascio delle 11:10). Il
 * disco resta locale (con Spaces i caricamenti temporanei andavano in 500):
 * qui si dice alla redazione di ricaricare il file, al posto di un errore che
 * non spiega niente. Resta una riga di log, perché Sentry non lo vede più.
 */
class UploadTemporaneoPerso
{
    public static function gestisci(Throwable $e, Closure $stopPropagation): void
    {
        $sparito = self::caricamentoSparito($e);

        if ($sparito === null) {
            return;
        }

        $stopPropagation();

        Log::warning('Pannello: caricamento temporaneo sparito prima del Salva', [
            'file' => $sparito->location(),
            'utente' => auth()->id(),
        ]);

        Notification::make()
            ->danger()
            ->title('Il file caricato non è più disponibile')
            ->body('Il caricamento è andato perso (il sito è stato aggiornato o è passato troppo tempo). Togli il file, caricalo di nuovo e salva: il resto del modulo è ancora qui.')
            ->persistent()
            ->send();
    }

    public static function eUnCaricamentoSparito(Throwable $e): bool
    {
        return self::caricamentoSparito($e) !== null;
    }

    /**
     * Guarda anche le eccezioni incapsulate: fra il disco e il Salva ci sono
     * Filament e la media library, che possono rilanciarle avvolte.
     */
    private static function caricamentoSparito(Throwable $e): UnableToRetrieveMetadata|UnableToReadFile|null
    {
        $cartella = FileUploadConfiguration::directory().'/';

        for ($corrente = $e; $corrente !== null; $corrente = $corrente->getPrevious()) {
            if (($corrente instanceof UnableToRetrieveMetadata || $corrente instanceof UnableToReadFile)
                && str_starts_with(ltrim($corrente->location(), '/'), $cartella)) {
                return $corrente;
            }
        }

        return null;
    }
}

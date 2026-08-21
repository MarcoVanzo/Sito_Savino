<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Toglie dall'archivio i documenti legali il cui file non esiste piu'.
 *
 * Il pannello salvava i file caricati sul disco locale del container invece
 * che su Spaces — Filament non segue FILESYSTEM_DISK e il suo valore di serie
 * e' quello locale — e in produzione il container viene ricreato a ogni
 * rilascio. In archivio e' rimasto il percorso, il file no: tutti e sette i
 * documenti legali rispondono "NoSuchKey", e nel footer erano link che non
 * aprivano niente.
 *
 * Svuotando la riga il campo torna vuoto nel pannello — che e' lo stato vero —
 * e la voce nel footer sparisce finche' il documento non viene ricaricato.
 * Il file in se' non e' recuperabile: va ricaricato dalla redazione.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (SiteSetting::all() as $impostazione) {
            if (! $this->eUnFileCaricato($impostazione->value)) {
                continue;
            }

            if ($this->manca($impostazione->value)) {
                $impostazione->update(['value' => null]);
            }
        }
    }

    private function eUnFileCaricato(mixed $valore): bool
    {
        if (! is_string($valore) || trim($valore) === '') {
            return false;
        }

        $valore = trim($valore);

        if (Str::startsWith($valore, ['http://', 'https://', '/', '#'])) {
            return false;
        }

        return (bool) preg_match('#^[\w\-/. ]+\.(pdf|jpe?g|png|webp|gif|svg|zip|docx?|xlsx?)$#i', $valore);
    }

    /**
     * Un disco che non risponde non e' un file mancante: in quel caso non si
     * tocca niente, altrimenti un problema di rete al momento sbagliato
     * cancellerebbe impostazioni valide.
     */
    private function manca(string $percorso): bool
    {
        try {
            return ! Storage::disk(config('filesystems.default'))->exists($percorso);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Non reversibile: rimettere il percorso di un file che non esiste
     * ricrearebbe il link rotto.
     */
    public function down(): void {}
};

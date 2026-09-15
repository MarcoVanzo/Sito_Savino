<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Concerns\PreservaContentData;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Creazione di una pagina in più lingue.
 *
 * Per le lingue diverse da quella attiva il plugin salva lo stato del
 * modulo così com'è: un Repeater finirebbe in archivio come mappa
 * `{uuid: voce}` e un file caricato resterebbe nella cartella temporanea.
 * `content_data` passa quindi dallo stesso trattamento della modifica.
 */
class CreatePage extends CreateRecord
{
    use PreservaContentData;
    use Translatable {
        handleRecordCreation as private creaIlRecordConIlPlugin;
    }

    protected static string $resource = PageResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->contentDataPreservato($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Da qui in poi il plugin chiama mutateFormDataBeforeCreate per le
        // altre lingue, i cui file caricati non sono ancora stati salvati.
        $this->salvandoUnAltraLingua = true;

        try {
            return $this->creaIlRecordConIlPlugin($data);
        } finally {
            $this->salvandoUnAltraLingua = false;
        }
    }
}

<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Concerns\PreservaContentData;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Modifica di una pagina in più lingue.
 *
 * Il plugin delle traduzioni idrata nel modulo solo la lingua di partenza:
 * le altre le tiene da parte così come stanno in archivio e le monta nel
 * modulo tali e quali quando si cambia lingua o quando si salva. Un
 * FileUpload però lavora su una mappa `{uuid: percorso}` e un Repeater su
 * `{uuid: voce}`: con il percorso nudo in stato, l'inglese di Safeguarding
 * mostrava un campo file grezzo e andava in errore al primo PDF caricato, e
 * in italiano il salvataggio saltava sulla validazione dell'inglese. Qui le
 * lingue arrivate grezze passano dal modulo prima di essere usate, e il
 * salvataggio delle altre lingue è riscritto per intero: serve sapere quale
 * lingua si sta salvando e, dopo, riallineare le copie tenute da parte al
 * record salvato (altrimenti un PDF caricato in inglese resterebbe "da
 * salvare" e verrebbe ricopiato su disco a ogni salvataggio successivo).
 */
class EditPage extends EditRecord
{
    use PreservaContentData;
    use Translatable {
        fillForm as private riempiIlModuloConIlPlugin;
        updatedActiveLocale as private cambiaLinguaConIlPlugin;
    }

    protected static string $resource = PageResource::class;

    /**
     * Lingue il cui stato è ancora quello grezzo dell'archivio.
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $lingueGrezze = [];

    protected function fillForm(): void
    {
        $this->riempiIlModuloConIlPlugin();

        $this->lingueGrezze = array_keys($this->otherLocaleData);
    }

    public function updatedActiveLocale(): void
    {
        $this->cambiaLinguaConIlPlugin();

        if (! in_array($this->activeLocale, $this->lingueGrezze, true)) {
            return;
        }

        // Solo le colonne tradotte: riempire il modulo intero rileggerebbe
        // dal record anche copertina, pagina genitore e autore, buttando via
        // quello che la redazione ha appena cambiato e non ancora salvato.
        $this->data = [...$this->data, ...$this->idrata(Arr::only($this->data, $this->colonneTradotte()))];
        $this->lingueGrezze = array_values(array_diff($this->lingueGrezze, [$this->activeLocale]));
    }

    /**
     * Come il plugin, con tre differenze: le lingue grezze vengono idratate
     * prima di validarle, `mutateFormDataBeforeSave` sa quale lingua sta
     * salvando, e alla fine le copie delle altre lingue si riallineano al
     * record.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $colonneTradotte = $this->colonneTradotte();

        $record->fill(Arr::except($data, $colonneTradotte));

        foreach (Arr::only($data, $colonneTradotte) as $chiave => $valore) {
            $record->setTranslation($chiave, $this->activeLocale, $valore);
        }

        $datiOriginali = $this->data;

        foreach ($this->otherLocaleData as $lingua => $valori) {
            if (in_array($lingua, $this->lingueGrezze, true)) {
                $valori = $this->idrata($valori);
            }

            $this->data = [...$datiOriginali, ...$valori];

            try {
                $this->form->validate();
            } catch (ValidationException) {
                // Come nel plugin: una lingua che non passa la validazione
                // (tipicamente senza titolo) si lascia com'è in archivio.
                $this->data = $datiOriginali;

                continue;
            }

            $this->linguaInSalvataggio = $lingua;
            $this->salvandoUnAltraLingua = true;

            try {
                $valori = $this->mutateFormDataBeforeSave($valori);
            } finally {
                $this->linguaInSalvataggio = null;
                $this->salvandoUnAltraLingua = false;
            }

            foreach (Arr::only($valori, $colonneTradotte) as $chiave => $valore) {
                $record->setTranslation($chiave, $lingua, $valore);
            }

            $this->data = $datiOriginali;
        }

        $this->lingueGrezze = [];

        $record->save();

        foreach (array_keys($this->otherLocaleData) as $lingua) {
            $this->otherLocaleData[$lingua] = $this->idrata($this->traduzioniDelRecord($record, $lingua));
        }

        return $record;
    }

    /**
     * Fa passare dal modulo i dati grezzi di una lingua, senza toccare quelli
     * della lingua attiva né i campi comuni a tutte le lingue.
     *
     * @param  array<string, mixed>  $valori
     * @return array<string, mixed>
     */
    private function idrata(array $valori): array
    {
        $originali = $this->data;

        $this->data = [...$this->data, ...$valori];
        $this->form->fill($this->data);

        $idratati = Arr::only($this->data, $this->colonneTradotte());

        $this->data = $originali;

        return $idratati;
    }

    /**
     * @return array<string, mixed>
     */
    private function traduzioniDelRecord(Model $record, string $lingua): array
    {
        $valori = [];

        foreach ($this->colonneTradotte() as $colonna) {
            $valori[$colonna] = $record->getTranslation($colonna, $lingua, false);
        }

        return $this->mutateFormDataBeforeFill($valori);
    }

    /**
     * @return array<int, string>
     */
    private function colonneTradotte(): array
    {
        return static::getResource()::getTranslatableAttributes();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->contentDataPreservato($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

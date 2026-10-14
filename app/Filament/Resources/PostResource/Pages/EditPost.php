<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Illuminate\Database\Eloquent\Model;

class EditPost extends EditRecord
{
    use Translatable {
        fillForm as fillTranslatableForm;
        handleRecordUpdate as handleTranslatableRecordUpdate;
    }

    protected static string $resource = PostResource::class;

    /**
     * Impronta di ogni campo tradotto, per lingua, come è stato caricato nel
     * modulo all'apertura della pagina.
     *
     * Il plugin translatable carica tutte le lingue all'apertura e a ogni
     * salvataggio le riscrive tutte, senza ricaricarle. Intanto la traduzione
     * automatica (`TraduciLaNotiziaInInglese`) può aver scritto un inglese
     * nuovo: il secondo salvataggio della stessa pagina lo riportava indietro,
     * e il job lo prendeva per una correzione della redazione. Un campo che
     * nel modulo è rimasto com'era caricato si lascia quindi come sta nel
     * database.
     *
     * @var array<string, array<string, string>>
     */
    public array $impronteCaricate = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function fillForm(): void
    {
        $this->fillTranslatableForm();

        $this->impronteCaricate = [];
        $campi = static::getResource()::getTranslatableAttributes();

        foreach ([$this->activeLocale => $this->data, ...$this->otherLocaleData] as $lingua => $dati) {
            foreach ($campi as $campo) {
                $this->impronteCaricate[$lingua][$campo] = self::impronta($dati[$campo] ?? null);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $attuale = $record->fresh() ?? $record;
        $campi = static::getResource()::getTranslatableAttributes();

        $data = $this->conIValoriDelDatabase($data, $this->activeLocale, $campi, $attuale);

        foreach ($this->otherLocaleData as $lingua => $dati) {
            $this->otherLocaleData[$lingua] = $this->conIValoriDelDatabase($dati, $lingua, $campi, $attuale);
        }

        return $this->handleTranslatableRecordUpdate($record, $data);
    }

    /**
     * @param  array<string, mixed>  $dati
     * @param  list<string>  $campi
     * @return array<string, mixed>
     */
    private function conIValoriDelDatabase(array $dati, string $lingua, array $campi, Model $attuale): array
    {
        foreach ($campi as $campo) {
            $caricata = $this->impronteCaricate[$lingua][$campo] ?? null;

            if (array_key_exists($campo, $dati) && $caricata !== null && self::impronta($dati[$campo]) === $caricata) {
                $dati[$campo] = $attuale->getTranslation($campo, $lingua, false);
            }
        }

        return $dati;
    }

    private static function impronta(mixed $valore): string
    {
        return sha1((string) json_encode($valore));
    }
}

<?php

namespace App\Filament\Pages\Settings;

use App\Enums\UserRole;
use App\Http\Middleware\CachePublicResponse;
use App\Models\SiteSetting;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;

abstract class BaseSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Pagine & Extra';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->role === UserRole::SuperAdmin;
    }

    protected static string $view = 'filament.pages.base-settings-page';

    public ?array $data = [];

    /**
     * Impostazioni il cui valore è un JSON per lingua (`{"it":…,"en":…}`).
     *
     * Nel form diventano un campo per lingua (`chiave.it`, `chiave.en`) e in
     * salvataggio tornano una riga sola. Senza questo passaggio il modulo
     * caricherebbe la sola lingua del pannello e la riscriverebbe come testo
     * semplice, cancellando le altre traduzioni.
     *
     * @return list<string>
     */
    protected function translatableKeys(): array
    {
        return [];
    }

    public function mount(): void
    {
        $this->data = [];

        // Le pagine che nominano i campi con la chiave nuda (`hero_title`).
        foreach (SiteSetting::getAllCached() as $key => $value) {
            data_set($this->data, $key, $value);
        }

        // Quelle che li nominano `gruppo.chiave` (`legal.privacy_policy`): il
        // gruppo sta nella sua colonna, non dentro la chiave, quindi va
        // ricomposto qui o il modulo si aprirebbe sempre vuoto.
        foreach (SiteSetting::getAllGrouped() as $group => $values) {
            foreach ($values as $key => $value) {
                data_set($this->data, $group.'.'.$key, $value);
            }
        }

        foreach ($this->translatableKeys() as $key) {
            $this->data[$key] = SiteSetting::perLocale($key);
        }
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($this->translatableKeys() as $key) {
            if (! is_array($data[$key] ?? null)) {
                continue;
            }

            $valori = [];

            foreach ($data[$key] as $locale => $valore) {
                // Gli elenchi (i repeater) arrivano indicizzati per chiave
                // interna: vanno rinumerati, o in JSON diventano un oggetto.
                $valori[$locale] = is_array($valore) ? array_values($valore) : (string) ($valore ?? '');
            }

            SiteSetting::set($key, $valori);
            unset($data[$key]);
        }

        $flat = Arr::dot($data);
        foreach ($flat as $key => $value) {
            SiteSetting::set($key, $value ?? '');
        }

        // Invalidate full page cache for public responses instantly
        if (class_exists(CachePublicResponse::class)) {
            CachePublicResponse::flush();
        }

        Notification::make()->title('Impostazioni salvate con successo')->success()->send();
    }
}

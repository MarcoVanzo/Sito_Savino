<?php

namespace App\Filament\Pages\Settings;

use App\Enums\UserRole;
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

    public function mount(): void
    {
        $flat = SiteSetting::getAllCached();
        $this->data = [];
        foreach ($flat as $key => $value) {
            data_set($this->data, $key, $value);
        }
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $flat = Arr::dot($data);
        foreach ($flat as $key => $value) {
            SiteSetting::set($key, $value ?? '');
        }
        Notification::make()->title('Impostazioni salvate con successo')->success()->send();
    }
}

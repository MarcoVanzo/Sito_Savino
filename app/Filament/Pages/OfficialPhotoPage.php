<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\RestrictsAccessByRole;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OfficialPhotoPage extends Page implements HasForms
{
    use InteractsWithForms;
    use RestrictsAccessByRole;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationLabel = 'Foto Ufficiale';

    protected static ?string $navigationGroup = 'Stagione';

    protected static ?string $title = 'Foto Ufficiale';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'foto-ufficiale';

    protected static string $view = 'filament.pages.official-photo-page';

    public ?array $data = [];

    public function mount(): void
    {
        // Con `fill()` e non assegnando `$this->data`: senza idratazione un
        // FileUpload con un file già in archivio manda in 500 la richiesta con
        // cui il browser chiede i file caricati (successe a Documenti Legali).
        $this->form->fill([
            'official_photo_pdf' => SiteSetting::getAllCached()['official_photo_pdf'] ?? null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Caricamento Documento')
                    ->description('Carica qui il PDF della Foto Ufficiale della Stagione')
                    ->schema([
                        FileUpload::make('official_photo_pdf')
                            ->label('PDF Foto Ufficiale')
                            ->acceptedFileTypes(['application/pdf'])
                            ->directory('official-photos')
                            ->maxSize(51200) // 50MB
                            ->helperText('Seleziona o trascina un file in formato PDF.'),
                    ]),
            ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (array_key_exists('official_photo_pdf', $data)) {
            $value = $data['official_photo_pdf'];
            if ($value) {
                SiteSetting::set('official_photo_pdf', $value);
            } else {
                SiteSetting::where('key', 'official_photo_pdf')->delete();
                SiteSetting::clearCache();
                // Senza PDF la voce "Foto Ufficiale" esce dal menu.
                MenuItem::clearCache();
            }
        }

        Notification::make()->title('Documento salvato con successo')->success()->send();
    }

    protected static function requiredAbility(): string
    {
        return 'canManageSport';
    }
}

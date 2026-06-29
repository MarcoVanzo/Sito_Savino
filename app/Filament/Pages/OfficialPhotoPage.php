<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OfficialPhotoPage extends Page implements HasForms
{
    use InteractsWithForms;

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
        $this->data = SiteSetting::getAllCached();
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
                    ])
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
            }
        }
        
        Notification::make()->title('Documento salvato con successo')->success()->send();
    }
}

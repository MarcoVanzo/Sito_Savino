<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;

class LegalSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Documenti Legali';

    protected static ?string $title = 'Documenti Legali';

    protected static ?int $navigationSort = 65;

    protected static ?string $slug = 'settings/legal';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Privacy e Policy')->schema([
                    self::pdfUpload('legal.privacy_policy', 'Privacy Policy'),
                    self::pdfUpload('legal.cookie_policy', 'Cookie Policy'),
                    self::pdfUpload('legal.informativa_fornitori', 'Informativa Fornitori'),
                ])->columns(3),
                Section::make('Corporate Governance')->schema([
                    self::pdfUpload('legal.modello_organizzativo', 'Modello Organizzativo'),
                    self::pdfUpload('legal.codice_tutela_minori', 'Codice Tutela Minori'),
                    self::pdfUpload('legal.protocollo_bullismo', 'Protocollo Bullismo'),
                    self::pdfUpload('legal.protocollo_razzismo', 'Protocollo Razzismo'),
                ])->columns(2),
            ])->statePath('data');
    }

    /**
     * I documenti legali sono tutti PDF caricati nella stessa cartella e con il
     * nome originale conservato: cambia solo la chiave e l'etichetta.
     */
    private static function pdfUpload(string $key, string $label): FileUpload
    {
        return FileUpload::make($key)
            ->label($label)
            ->acceptedFileTypes(['application/pdf'])
            ->directory('legal')
            ->preserveFilenames();
    }
}

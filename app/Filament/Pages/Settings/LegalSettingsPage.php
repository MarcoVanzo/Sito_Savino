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
     *
     * Qui non ci sono piu' le informative: Privacy Policy, Cookie Policy,
     * comunicazioni promozionali e fornitori sono pagine (Pagine), come tutte
     * le altre voci legali del footer.
     *
     * Privacy Policy e Cookie Policy del sito: erano
     * due PDF ereditati dal vecchio WordPress — quello sui cookie elencava i
     * cookie di un plugin e di Universal Analytics che qui non esistono — e il
     * footer li preferiva alle pagine, che sono la versione mantenuta dalla
     * redazione e quella che i moduli fanno accettare. Due informative diverse
     * per lo stesso sito, e la peggiore in prima fila.
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

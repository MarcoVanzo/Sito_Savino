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
                    FileUpload::make('legal.privacy_policy')->label('Privacy Policy')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                    FileUpload::make('legal.cookie_policy')->label('Cookie Policy')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                    FileUpload::make('legal.informativa_fornitori')->label('Informativa Fornitori')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                ])->columns(3),
                Section::make('Corporate Governance')->schema([
                    FileUpload::make('legal.modello_organizzativo')->label('Modello Organizzativo')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                    FileUpload::make('legal.codice_tutela_minori')->label('Codice Tutela Minori')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                    FileUpload::make('legal.protocollo_bullismo')->label('Protocollo Bullismo')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                    FileUpload::make('legal.protocollo_razzismo')->label('Protocollo Razzismo')->acceptedFileTypes(['application/pdf'])->directory('legal')->preserveFilenames(),
                ])->columns(2),
            ])->statePath('data');
    }
}

<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

/**
 * Recapiti e dati societari: sono le voci del gruppo `contact`, quelle che il
 * sito usa nel footer, nella pagina Contatti, in Comunicazione e nel settore
 * giovanile.
 *
 * Prima esistevano solo in tabella: la pagina CMS "Contatti" mostrava campi con
 * gli stessi nomi ma li salvava dentro `content_data`, dove nessuno li legge —
 * si modificava l'indirizzo e online restava quello vecchio.
 */
class ContattiSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Contatti';

    protected static ?string $title = 'Recapiti e Dati Societari';

    protected static ?int $navigationSort = 61;

    protected static ?string $slug = 'settings/contatti';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Recapiti generali')
                    ->description('Compaiono nel footer di ogni pagina e nella pagina Contatti.')
                    ->schema([
                        TextInput::make('email')->label('Email principale')->email(),
                        TextInput::make('pec')->label('PEC')->email(),
                        TextInput::make('phone')->label('Telefono')->tel(),
                        TextInput::make('office_hours')->label('Orari di apertura'),
                    ])->columns(2),

                Section::make('Sede')->schema([
                    TextInput::make('address')->label('Indirizzo'),
                    TextInput::make('city')->label('Città / Località'),
                ])->columns(2),

                Section::make('Email di reparto')
                    ->description('Usate dalle pagine Comunicazione, Sponsor e Settore Giovanile quando non ne indicano una propria.')
                    ->schema([
                        TextInput::make('press_email')->label('Ufficio stampa')->email(),
                        TextInput::make('social_email')->label('Social media')->email(),
                        TextInput::make('media_email')->label('Media e accrediti')->email(),
                        TextInput::make('youth_email')->label('Settore giovanile')->email(),
                    ])->columns(2),

                Section::make('Dati fiscali e sportivi')->schema([
                    TextInput::make('legal_piva')->label('Partita IVA'),
                    TextInput::make('legal_cf')->label('Codice fiscale'),
                    TextInput::make('legal_fipav')->label('Codice affiliazione FIPAV'),
                    TextInput::make('legal_sdi')->label('Codice univoco (SDI)'),
                ])->columns(2),
            ])->statePath('data');
    }
}

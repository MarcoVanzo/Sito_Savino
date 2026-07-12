<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class ContattiSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-phone';

    protected static ?string $navigationLabel = 'Contatti';

    protected static ?string $title = 'Impostazioni Contatti';

    protected static ?int $navigationSort = 61;

    protected static ?string $slug = 'settings/contatti';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('ContactTabs')
                    ->tabs([
                        Tabs\Tab::make('Contatti Principali')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                TextInput::make('email')->label('Email Principale')->email(),
                                TextInput::make('phone')->label('Telefono Sede'),
                                TextInput::make('pec')->label('PEC Principale')->email(),
                                TextInput::make('address')->label('Indirizzo'),
                                TextInput::make('city')->label('Città'),
                                TextInput::make('office_hours')->label('Orari di Apertura'),
                            ])->columns(2),
                        Tabs\Tab::make('Dati Societari / Legali')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                TextInput::make('legal_piva')->label('Partita IVA'),
                                TextInput::make('legal_cf')->label('Codice Fiscale'),
                                TextInput::make('legal_fipav')->label('Codice FIPAV'),
                                TextInput::make('legal_sdi')->label('Codice SDI'),
                            ])->columns(2),
                        Tabs\Tab::make('Dipartimenti (Email)')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                TextInput::make('press_email')->label('Ufficio Stampa')->email(),
                                TextInput::make('social_email')->label('Social Media')->email(),
                                TextInput::make('media_email')->label('Media & Accrediti')->email(),
                                TextInput::make('youth_email')->label('Settore Giovanile')->email(),
                            ])->columns(2),
                    ])->columnSpanFull()
            ])->statePath('data');
    }
}

<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\Section;
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
                Section::make('Principali')->schema([
                    TextInput::make('email')->label('Email')->email(),
                    TextInput::make('phone')->label('Telefono'),
                    TextInput::make('pec')->label('PEC')->email(),
                    TextInput::make('address')->label('Indirizzo'),
                    TextInput::make('city')->label('Città'),
                    TextInput::make('office_hours')->label('Orari'),
                ])->columns(2),
                Section::make('Dipartimenti')->schema([
                    TextInput::make('press_email')->label('Stampa')->email(),
                    TextInput::make('social_email')->label('Social')->email(),
                    TextInput::make('media_email')->label('Media')->email(),
                    TextInput::make('youth_email')->label('Giovanili')->email(),
                ])->columns(2),
            ])->statePath('data');
    }
}

<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class GeneraliSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Generali';

    protected static ?string $title = 'Impostazioni Generali';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'settings/generali';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Corporate')->schema([
                    TextInput::make('corporate_logo')->label('Logo Corporate'),
                    TextInput::make('corporate_url')->label('URL Corporate')->url(),
                    TextInput::make('corporate_name')->label('Nome Corporate'),
                    TextInput::make('corporate_description')->label('Descrizione Corporate'),
                ])->columns(2),
            ])->statePath('data');
    }
}

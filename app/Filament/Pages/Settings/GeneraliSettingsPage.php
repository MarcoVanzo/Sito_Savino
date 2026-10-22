<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeneraliSettingsPage extends BaseSettingsPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Generali';

    protected static ?string $title = 'Impostazioni Generali';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'settings/generali';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sito')->schema([
                    TextInput::make('site_logo')
                        ->label('Logo del sito')
                        ->helperText('Percorso o indirizzo dell\'immagine. Se vuoto viene usato il logo ufficiale della società.'),
                ]),

                Section::make('Corporate')->schema([
                    TextInput::make('corporate_logo')->label('Logo Corporate'),
                    TextInput::make('corporate_url')->label('URL Corporate')->url(),
                    TextInput::make('corporate_name')->label('Nome Corporate'),
                    TextInput::make('corporate_description')->label('Descrizione Corporate'),
                ])->columns(2),
            ])->statePath('data');
    }
}

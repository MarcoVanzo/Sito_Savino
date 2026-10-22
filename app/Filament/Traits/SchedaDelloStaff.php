<?php

namespace App\Filament\Traits;

use App\Enums\StaffType;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;

/**
 * Parti comuni delle schede dello staff (prima squadra, vivaio e
 * dirigenza): anagrafica, foto del profilo e colonne dell'elenco.
 */
trait SchedaDelloStaff
{
    /**
     * Anagrafica di tecnici e medici; `$sezione` dice in quale area del
     * sito compare la persona (`a1` o `youth`).
     */
    protected static function anagraficaDelloStaff(string $sezione): Section
    {
        return Section::make('Anagrafica e Ruolo')
            ->schema([
                TextInput::make('first_name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Cognome')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Sezione (Tipologia)')
                    ->options([
                        StaffType::Tecnico->value => StaffType::Tecnico->label(),
                        StaffType::Medico->value => StaffType::Medico->label(),
                    ])
                    ->required()
                    ->helperText('Determina in quale pagina del sito comparirà.'),
                TextInput::make('role')
                    ->label('Ruolo specifico')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Es. Allenatore, Presidente, Medico...'),
                Hidden::make('section')
                    ->default($sezione),
            ])->columns(2);
    }

    protected static function fotoDelProfilo(): Section
    {
        return Section::make('Foto Profilo')
            ->schema([
                SpatieMediaLibraryFileUpload::make('avatar')
                    ->label('Immagine di Profilo (Avatar)')
                    ->collection('staff')
                    ->image()
                    ->maxSize(5120)
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth('1200')
                    ->imageResizeTargetHeight('1200')
                    ->imageResizeUpscale(false)
                    ->helperText('Immagine ottimale: quadrata (es. 800x800px).')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, Column>
     */
    protected static function colonneDelloStaff(): array
    {
        return [
            SpatieMediaLibraryImageColumn::make('avatar')
                ->checkFileExistence(false)
                ->conversion('thumb')
                ->label('')
                ->collection('staff')
                ->circular(),
            TextColumn::make('first_name')
                ->label('Nome')
                ->searchable(),
            TextColumn::make('last_name')
                ->label('Cognome')
                ->searchable(),
            TextColumn::make('role')
                ->label('Ruolo')
                ->searchable(),
            TextColumn::make('type')
                ->label('Sezione')
                ->badge()
                ->sortable(),
        ];
    }
}

<?php

namespace App\Filament\Resources\TeamResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RostersRelationManager extends RelationManager
{
    protected static string $relationship = 'rosters';
    protected static ?string $title = 'Roster';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('player_id')
                ->label('Giocatrice')
                ->relationship('player', 'last_name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('season_id')
                ->label('Stagione')
                ->relationship('season', 'name')
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('jersey_number')
                ->label('Numero Maglia')
                ->numeric()
                ->minValue(1)
                ->maxValue(99),
            Forms\Components\Select::make('role')
                ->label('Ruolo')
                ->options(\App\Enums\PlayerPosition::class)
                ->required(),
            Forms\Components\TextInput::make('height_cm')
                ->label('Altezza (cm)')
                ->numeric(),
            Forms\Components\Toggle::make('is_captain')
                ->label('Capitano'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('player.full_name')
                    ->label('Giocatrice')
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('season.name')
                    ->label('Stagione'),
                Tables\Columns\TextColumn::make('jersey_number')
                    ->label('N°'),
                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo'),
                Tables\Columns\IconColumn::make('is_captain')
                    ->label('Cap.')
                    ->boolean(),
            ])
            ->defaultSort('season_id', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

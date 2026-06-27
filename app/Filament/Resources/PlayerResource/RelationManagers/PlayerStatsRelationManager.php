<?php

namespace App\Filament\Resources\PlayerResource\RelationManagers;

use App\Filament\Traits\HasStandardTableActions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PlayerStatsRelationManager extends RelationManager
{
    use HasStandardTableActions;

    protected static string $relationship = 'stats';

    protected static ?string $title = 'Statistiche';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name')
                    ->required(),
                Forms\Components\TextInput::make('points')
                    ->label('Punti')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('aces')
                    ->label('Aces')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('blocks')
                    ->label('Muri')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('attacks')
                    ->label('Attacchi')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('receptions')
                    ->label('Ricezioni')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('season.name')
                    ->label('Stagione')
                    ->sortable(),
                Tables\Columns\TextColumn::make('points')
                    ->label('Punti')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('aces')
                    ->label('Aces')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('blocks')
                    ->label('Muri')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attacks')
                    ->label('Attacchi')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receptions')
                    ->label('Ricezioni')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions(static::standardBulkActions());
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerStatResource\Pages;
use App\Filament\Resources\PlayerStatResource\RelationManagers;
use App\Models\PlayerStat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlayerStatResource extends Resource
{
    protected static ?string $model = PlayerStat::class;

    protected static ?string $modelLabel = 'Statistica Partita';
    protected static ?string $pluralModelLabel = 'Statistiche Partite';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Gestione Sportiva';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('player_id')
                    ->relationship('player', 'last_name')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required(),
                Forms\Components\TextInput::make('points')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('blocks')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('aces')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('attacks')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('receptions')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\DateTimePicker::make('last_synced_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('player.last_name')
                    ->label('Giocatrice')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('season.name')
                    ->label('Stagione')
                    ->sortable(),
                Tables\Columns\TextColumn::make('points')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('blocks')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('aces')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attacks')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('receptions')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayerStats::route('/'),
            'create' => Pages\CreatePlayerStat::route('/create'),
            'edit' => Pages\EditPlayerStat::route('/{record}/edit'),
        ];
    }
}

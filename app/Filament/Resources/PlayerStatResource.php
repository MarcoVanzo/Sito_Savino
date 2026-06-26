<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerStatResource\Pages;
use App\Models\PlayerStat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlayerStatResource extends Resource
{
    protected static ?string $model = PlayerStat::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Statistica Partita';
    protected static ?string $pluralModelLabel = 'Statistiche Partite';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Gestione Sportiva';
    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Forms\Components\TextInput::make('points')
                    ->label('Punti')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\TextInput::make('blocks')
                    ->label('Muri')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\TextInput::make('aces')
                    ->label('Ace')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\TextInput::make('attacks')
                    ->label('Attacchi')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\TextInput::make('receptions')
                    ->label('Ricezioni')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Forms\Components\DateTimePicker::make('last_synced_at')
                    ->label('Ultimo Sync'),
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
                Tables\Actions\ViewAction::make(),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['player', 'season']);
    }
}

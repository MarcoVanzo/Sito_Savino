<?php

namespace App\Filament\Resources;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Filament\Resources\GameResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Game;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GameResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Game::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'competition_type';

    protected static ?string $modelLabel = 'Partita';

    protected static ?string $pluralModelLabel = 'Partite';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Risultati e Classifica';

    protected static ?string $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'partite';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Partita')
                    ->schema([
                        Forms\Components\Select::make('season_id')
                            ->label('Stagione')
                            ->relationship('season', 'name')
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('competition_type')
                            ->label('Competizione')
                            ->options(CompetitionType::class)
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Stato')
                            ->options(GameStatus::class)
                            ->default(GameStatus::Scheduled)
                            ->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Squadre e Risultato')
                    ->schema([
                        Forms\Components\Select::make('home_team_id')
                            ->label('Squadra in Casa')
                            ->relationship('homeTeam', 'name')
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('home_score')
                            ->label('Punti (Casa)')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\Select::make('away_team_id')
                            ->label('Squadra in Trasferta')
                            ->relationship('awayTeam', 'name')
                            ->required()
                            ->different('home_team_id'),
                        Forms\Components\TextInput::make('away_score')
                            ->label('Punti (Trasferta)')
                            ->numeric()
                            ->minValue(0),
                    ])->columns(2),
                Forms\Components\Section::make('Programmazione')
                    ->schema([
                        Forms\Components\DateTimePicker::make('match_date')
                            ->label('Data e Ora Incontro')
                            ->required(),
                        Forms\Components\TextInput::make('location')
                            ->label('Luogo / Palazzetto')
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('match_date')
                    ->label('Data e Ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->location),
                Tables\Columns\TextColumn::make('competition_type')
                    ->label('Competizione')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        \App\Enums\GameStatus::Scheduled->value => 'gray',
                        \App\Enums\GameStatus::Live->value => 'danger',
                        \App\Enums\GameStatus::Finished->value => 'success',
                        \App\Enums\GameStatus::Cancelled->value => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('homeTeam.name')
                    ->label('Casa')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('home_score')
                    ->label('Pt.')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('away_score')
                    ->label('Pt.')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('awayTeam.name')
                    ->label('Trasferta')
                    ->searchable()
                    ->weight('bold'),
            ])
            ->defaultSort('match_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('competition_type')
                    ->label('Competizione')
                    ->options(CompetitionType::class),
                Tables\Filters\SelectFilter::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name'),
            ])
            ->actions(static::viewAndEditActions())
            ->bulkActions(static::standardBulkActions());
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
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['homeTeam', 'awayTeam', 'season']);
    }
}

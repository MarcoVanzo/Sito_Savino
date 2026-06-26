<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages;
use App\Models\Game;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Sport';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required(),
                Forms\Components\Select::make('competition_type')
                    ->options([
                        'Campionato' => 'Campionato',
                        'Coppa Italia' => 'Coppa Italia',
                        'Champions League' => 'Champions League',
                        'Amichevole' => 'Amichevole',
                    ]),
                Forms\Components\Select::make('home_team_id')
                    ->relationship('homeTeam', 'name')
                    ->required(),
                Forms\Components\Select::make('away_team_id')
                    ->relationship('awayTeam', 'name')
                    ->required(),
                Forms\Components\DateTimePicker::make('match_date')
                    ->required(),
                Forms\Components\TextInput::make('home_score')
                    ->numeric(),
                Forms\Components\TextInput::make('away_score')
                    ->numeric(),
                Forms\Components\TextInput::make('location')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('match_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('homeTeam.name'),
                Tables\Columns\TextColumn::make('awayTeam.name'),
                Tables\Columns\TextColumn::make('home_score'),
                Tables\Columns\TextColumn::make('away_score'),
                Tables\Columns\TextColumn::make('competition_type'),
            ])
            ->filters([
                //
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
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}

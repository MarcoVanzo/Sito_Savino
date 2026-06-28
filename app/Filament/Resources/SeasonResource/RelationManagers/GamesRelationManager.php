<?php

namespace App\Filament\Resources\SeasonResource\RelationManagers;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class GamesRelationManager extends RelationManager
{
    protected static string $relationship = 'games';

    protected static ?string $title = 'Partite';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('home_team_id')
                ->label('Casa')
                ->relationship('homeTeam', 'name')
                ->required(),
            Forms\Components\Select::make('away_team_id')
                ->label('Trasferta')
                ->relationship('awayTeam', 'name')
                ->required(),
            Forms\Components\DateTimePicker::make('match_date')
                ->label('Data e Ora')
                ->required(),
            Forms\Components\Select::make('competition_type')
                ->label('Competizione')
                ->options(CompetitionType::class)
                ->required(),
            Forms\Components\Select::make('status')
                ->label('Stato')
                ->options(GameStatus::class)
                ->default(GameStatus::Scheduled),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('match_date')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('homeTeam.name')
                    ->label('Casa'),
                Tables\Columns\TextColumn::make('home_score')->label('Pt.'),
                Tables\Columns\TextColumn::make('away_score')->label('Pt.'),
                Tables\Columns\TextColumn::make('awayTeam.name')
                    ->label('Trasferta'),
                Tables\Columns\TextColumn::make('competition_type')
                    ->label('Comp.'),
            ])
            ->defaultSort('match_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

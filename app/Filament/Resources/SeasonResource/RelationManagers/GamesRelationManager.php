<?php

namespace App\Filament\Resources\SeasonResource\RelationManagers;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GamesRelationManager extends RelationManager
{
    protected static string $relationship = 'games';

    protected static ?string $title = 'Partite';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('home_team_id')
                ->label('Casa')
                ->relationship('homeTeam', 'name')
                ->required(),
            Select::make('away_team_id')
                ->label('Trasferta')
                ->relationship('awayTeam', 'name')
                ->required(),
            DateTimePicker::make('match_date')
                ->label('Data e Ora')
                ->required(),
            Select::make('competition_type')
                ->label('Competizione')
                ->options(CompetitionType::class)
                ->required(),
            Select::make('status')
                ->label('Stato')
                ->options(GameStatus::class)
                ->default(GameStatus::Scheduled),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['homeTeam', 'awayTeam']))
            ->columns([
                TextColumn::make('match_date')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('homeTeam.name')
                    ->label('Casa'),
                TextColumn::make('home_score')->label('Pt.'),
                TextColumn::make('away_score')->label('Pt.'),
                TextColumn::make('awayTeam.name')
                    ->label('Trasferta'),
                TextColumn::make('competition_type')
                    ->label('Comp.'),
            ])
            ->defaultSort('match_date', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\TeamResource\RelationManagers;

use App\Enums\PlayerPosition;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RostersRelationManager extends RelationManager
{
    protected static string $relationship = 'rosters';

    protected static ?string $title = 'Roster';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('player_id')
                ->label('Giocatrice')
                ->relationship('player', 'last_name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('season_id')
                ->label('Stagione')
                ->relationship('season', 'name')
                ->preload()
                ->required(),
            TextInput::make('jersey_number')
                ->label('Numero Maglia')
                ->numeric()
                ->minValue(1)
                ->maxValue(99),
            Select::make('role')
                ->label('Ruolo')
                ->options(PlayerPosition::class)
                ->required(),
            TextInput::make('height_cm')
                ->label('Altezza (cm)')
                ->numeric(),
            Toggle::make('is_captain')
                ->label('Capitano'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['player', 'season']))
            ->columns([
                TextColumn::make('player.full_name')
                    ->label('Giocatrice')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('season.name')
                    ->label('Stagione'),
                TextColumn::make('jersey_number')
                    ->label('N°'),
                TextColumn::make('role')
                    ->label('Ruolo'),
                IconColumn::make('is_captain')
                    ->label('Cap.')
                    ->boolean(),
            ])
            ->defaultSort('season_id', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

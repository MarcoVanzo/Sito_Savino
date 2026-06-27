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
        $statFields = collect([
            'points' => 'Punti', 'aces' => 'Aces', 'blocks' => 'Muri',
            'attacks' => 'Attacchi', 'receptions' => 'Ricezioni',
        ])->map(fn ($label, $name) => Forms\Components\TextInput::make($name)
            ->label($label)->required()->numeric()->default(0)
        )->values()->all();

        return $form
            ->schema([
                Forms\Components\Select::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name')
                    ->required(),
                ...$statFields,
            ]);
    }

    public function table(Table $table): Table
    {
        $statColumns = collect([
            'points' => 'Punti', 'aces' => 'Aces', 'blocks' => 'Muri',
            'attacks' => 'Attacchi', 'receptions' => 'Ricezioni',
        ])->map(fn ($label, $name) => Tables\Columns\TextColumn::make($name)
            ->label($label)->numeric()->sortable()
        )->values()->all();

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('season.name')
                    ->label('Stagione')
                    ->sortable(),
                ...$statColumns,
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

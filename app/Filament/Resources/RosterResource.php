<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RosterResource\Pages;
use App\Models\Roster;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class RosterResource extends Resource
{
    protected static ?string $model = Roster::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Sport';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('player_id')
                    ->relationship('player', 'last_name')
                    ->required(),
                Forms\Components\Select::make('team_id')
                    ->relationship('team', 'name')
                    ->required(),
                Forms\Components\Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required(),
                Forms\Components\TextInput::make('jersey_number')
                    ->numeric(),
                Forms\Components\TextInput::make('role')
                    ->maxLength(255),
                Forms\Components\TextInput::make('height_cm')
                    ->numeric(),
                Forms\Components\Toggle::make('is_captain')
                    ->required(),
                Forms\Components\Textarea::make('bio')
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('official_photo')
                    ->collection('rosters_official')
                    ->image(),
                SpatieMediaLibraryFileUpload::make('action_photo')
                    ->collection('rosters_action')
                    ->image(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('player.last_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('season.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jersey_number'),
                Tables\Columns\TextColumn::make('role'),
                Tables\Columns\IconColumn::make('is_captain')
                    ->boolean(),
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
            'index' => Pages\ListRosters::route('/'),
            'create' => Pages\CreateRoster::route('/create'),
            'edit' => Pages\EditRoster::route('/{record}/edit'),
        ];
    }
}

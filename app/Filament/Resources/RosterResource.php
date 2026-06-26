<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RosterResource\Pages;
use App\Filament\Resources\RosterResource\RelationManagers;
use App\Models\Roster;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RosterResource extends Resource
{
    protected static ?string $model = Roster::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('player_id')
                    ->relationship('player', 'last_name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('team_id')
                    ->relationship('team', 'name')
                    ->required(),
                Forms\Components\Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required(),
                Forms\Components\TextInput::make('jersey_number')
                    ->numeric(),
                Forms\Components\Select::make('role')
                    ->options([
                        'Palleggiatrice' => 'Palleggiatrice',
                        'Centrale' => 'Centrale',
                        'Schiacciatrice' => 'Schiacciatrice',
                        'Opposta' => 'Opposta',
                        'Libero' => 'Libero',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('height_cm')
                    ->numeric(),
                Forms\Components\Toggle::make('is_captain')
                    ->required(),
                Forms\Components\FileUpload::make('official_photo_url')
                    ->disk('public')
                    ->directory('images/roster')
                    ->image(),
                Forms\Components\FileUpload::make('action_photo_url')
                    ->disk('public')
                    ->directory('images/roster')
                    ->image(),
                Forms\Components\Textarea::make('bio')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('player.last_name')
                    ->label('Giocatrice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Squadra')
                    ->sortable(),
                Tables\Columns\TextColumn::make('season.name')
                    ->label('Stagione')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jersey_number')
                    ->label('Numero')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('official_photo_url')
                    ->label('Foto')
                    ->disk('public'),
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

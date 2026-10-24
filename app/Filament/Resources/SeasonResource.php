<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeasonResource\Pages\CreateSeason;
use App\Filament\Resources\SeasonResource\Pages\EditSeason;
use App\Filament\Resources\SeasonResource\Pages\ListSeasons;
use App\Filament\Resources\SeasonResource\RelationManagers\GamesRelationManager;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Season;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SeasonResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Season::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Stagione';

    protected static ?string $pluralModelLabel = 'Stagioni';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                // Le altre stagioni vengono disattivate DOPO il salvataggio
                // (vedi CreateSeason/EditSeason): farlo al cambio di stato lasciava
                // il sito senza stagione corrente se poi si annullava la modifica.
                Toggle::make('is_current')
                    ->label('Stagione Corrente')
                    ->helperText('Attivandola, le altre stagioni verranno disattivate al salvataggio.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('is_current')
                    ->boolean(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ...static::timestampColumns(),
            ])
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('is_current')
                    ->label('Stagione Corrente'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions(static::softDeleteBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            GamesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeasons::route('/'),
            'create' => CreateSeason::route('/create'),
            'edit' => EditSeason::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

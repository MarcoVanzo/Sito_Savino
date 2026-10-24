<?php

namespace App\Filament\Resources;

use App\Enums\CompetitionType;
use App\Filament\Resources\StandingResource\Pages\ListStandings;
use App\Models\Season;
use App\Models\Standing;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Classifica importata dal sito della Lega.
 *
 * Risorsa di SOLA LETTURA: la sincronizzazione riscrive le righe a ogni giro,
 * quindi qualsiasi modifica fatta qui andrebbe persa al giro successivo. Non
 * esistono pagine di creazione/modifica né azioni di scrittura, e la cosa è
 * dichiarata all'utente (sottotitolo della lista + stato vuoto), altrimenti
 * l'assenza del pulsante "Nuovo" sembrerebbe un bug.
 */
class StandingResource extends Resource
{
    protected static ?string $model = Standing::class;

    protected static ?string $modelLabel = 'Classifica';

    protected static ?string $pluralModelLabel = 'Classifica';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Classifica';

    // Stesso gruppo di GameResource, subito dopo calendario e risultati.
    protected static string|\UnitEnum|null $navigationGroup = 'Stagione';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'classifica';

    protected static ?string $recordTitleAttribute = 'position';

    protected static bool $isGloballySearchable = false;

    /**
     * Nessun form: la risorsa non ha pagine di creazione o modifica.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')
                    ->label('Pos.')
                    ->alignCenter()
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                ImageColumn::make('team_logo')
                    ->label('')
                    ->checkFileExistence(false)
                    ->height(28)
                    ->getStateUsing(fn (Standing $record) => $record->team?->logoUrl()),

                TextColumn::make('team.name')
                    ->label('Squadra')
                    ->searchable()
                    ->sortable()
                    // Doppia evidenza per la squadra della società: oltre alla
                    // riga colorata (recordClasses), il nome è in grassetto con
                    // icona. Così resta riconoscibile anche dove il tema CSS
                    // non fosse ancora stato ricompilato.
                    ->weight(fn (Standing $record) => $record->team?->is_internal ? FontWeight::Bold : null)
                    ->color(fn (Standing $record) => $record->team?->is_internal ? 'primary' : null)
                    ->icon(fn (Standing $record) => $record->team?->is_internal ? 'heroicon-m-star' : null)
                    ->tooltip(fn (Standing $record) => $record->team?->is_internal ? 'Squadra della società' : null),

                TextColumn::make('points')
                    ->label('Punti')
                    ->alignCenter()
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('played')
                    ->label('G')
                    ->tooltip('Partite giocate')
                    ->alignCenter(),

                TextColumn::make('won')
                    ->label('V')
                    ->tooltip('Vinte')
                    ->alignCenter()
                    ->color('success'),

                TextColumn::make('lost')
                    ->label('P')
                    ->tooltip('Perse')
                    ->alignCenter()
                    ->color('danger'),

                TextColumn::make('won_3_0')
                    ->label('3-0')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('won_3_1')
                    ->label('3-1')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('won_3_2')
                    ->label('3-2')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lost_2_3')
                    ->label('2-3')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lost_1_3')
                    ->label('1-3')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lost_0_3')
                    ->label('0-3')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sets_won')
                    ->label('Set')
                    ->alignCenter()
                    ->formatStateUsing(fn (Standing $record) => "{$record->sets_won}-{$record->sets_lost}")
                    ->tooltip('Set vinti - set persi'),

                TextColumn::make('set_ratio')
                    ->label('Q. set')
                    ->alignCenter()
                    ->numeric(decimalPlaces: 3)
                    ->tooltip('Quoziente set')
                    ->toggleable(),

                TextColumn::make('points_for')
                    ->label('Punti fatti/subiti')
                    ->alignCenter()
                    ->formatStateUsing(fn (Standing $record) => "{$record->points_for}-{$record->points_against}")
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('point_ratio')
                    ->label('Q. punti')
                    ->alignCenter()
                    ->numeric(decimalPlaces: 3)
                    ->tooltip('Quoziente punti')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('competition_type')
                    ->label('Competizione')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('season.name')
                    ->label('Stagione')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('synced_at')
                    ->label('Aggiornata il')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('mai')
                    ->description(fn (Standing $record) => $record->synced_at?->diffForHumans())
                    ->sortable(),
            ])
            ->defaultSort('position')
            // La riga della nostra squadra si distingue dalle altre a colpo
            // d'occhio: le classi sono generate perché il tema Filament
            // scansiona app/Filament/**/*.php.
            ->recordClasses(fn (Standing $record) => $record->team?->is_internal
                ? 'bg-primary-50 dark:bg-primary-500/10'
                : null)
            ->filters([
                SelectFilter::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name')
                    ->default(Season::query()->where('is_current', true)->value('id')),
                SelectFilter::make('competition_type')
                    ->label('Competizione')
                    ->options(CompetitionType::class),
            ])
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make(),
            ])
            // Nessuna bulk action: i dati sono rigenerati dalla sincronizzazione.
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-list-bullet')
            ->emptyStateHeading('Nessuna classifica per questi filtri')
            ->emptyStateDescription(
                'La classifica non si inserisce a mano: viene importata dal sito della Lega '
                .'dalla sincronizzazione. Se è vuota, la sincronizzazione non è ancora stata '
                .'eseguita per la stagione o la competizione selezionata.'
            );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Squadra')
                    ->schema([
                        TextEntry::make('position')->label('Posizione'),
                        TextEntry::make('team.name')->label('Squadra'),
                        TextEntry::make('season.name')->label('Stagione'),
                        TextEntry::make('competition_type')
                            ->label('Competizione')
                            ->badge(),
                    ])->columns(4),

                Section::make('Rendimento')
                    ->schema([
                        TextEntry::make('points')->label('Punti'),
                        TextEntry::make('played')->label('Giocate'),
                        TextEntry::make('won')->label('Vinte'),
                        TextEntry::make('lost')->label('Perse'),
                        TextEntry::make('won_3_0')->label('Vinte 3-0'),
                        TextEntry::make('won_3_1')->label('Vinte 3-1'),
                        TextEntry::make('won_3_2')->label('Vinte 3-2'),
                        TextEntry::make('lost_2_3')->label('Perse 2-3'),
                        TextEntry::make('lost_1_3')->label('Perse 1-3'),
                        TextEntry::make('lost_0_3')->label('Perse 0-3'),
                    ])->columns(5),

                Section::make('Set e punti')
                    ->schema([
                        TextEntry::make('sets_won')->label('Set vinti'),
                        TextEntry::make('sets_lost')->label('Set persi'),
                        TextEntry::make('set_ratio')
                            ->label('Quoziente set')
                            ->numeric(decimalPlaces: 3),
                        TextEntry::make('points_for')->label('Punti fatti'),
                        TextEntry::make('points_against')->label('Punti subiti'),
                        TextEntry::make('point_ratio')
                            ->label('Quoziente punti')
                            ->numeric(decimalPlaces: 3),
                    ])->columns(3),

                Section::make('Sincronizzazione')
                    ->description('Riga generata dall\'import dal sito della Lega: non è modificabile dal CMS.')
                    ->schema([
                        TextEntry::make('synced_at')
                            ->label('Ultimo aggiornamento')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('mai'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        // Solo l'elenco: niente create/edit, i dati arrivano dalla Lega.
        return [
            'index' => ListStandings::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['team.media', 'season']);
    }

    // ─── Sola lettura ──────────────────────────────────────────
    // Oltre alla policy, il blocco è ripetuto a livello di risorsa: così le
    // azioni non compaiono nemmeno se un domani la policy venisse allentata.

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}

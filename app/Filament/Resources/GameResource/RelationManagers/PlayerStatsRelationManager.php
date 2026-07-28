<?php

namespace App\Filament\Resources\GameResource\RelationManagers;

use App\Filament\Resources\PlayerResource;
use App\Models\GamePlayerStat;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabellino della gara: le righe di ENTRAMBE le squadre.
 *
 * Scelto un RelationManager e non una sezione nell'infolist perché il tabellino
 * è una tabella di ~28 righe e ~20 colonne: qui si ottengono gratis
 * raggruppamento per squadra, ordinamento, colonne attivabili e ricerca, e la
 * query resta separata da quella della scheda gara (si carica solo quando serve).
 *
 * Sola lettura: i dati sono riscritti a ogni sincronizzazione del tabellino.
 */
class PlayerStatsRelationManager extends RelationManager
{
    protected static string $relationship = 'playerStats';

    protected static ?string $title = 'Tabellino';

    protected static ?string $icon = 'heroicon-o-table-cells';

    protected static ?string $recordTitleAttribute = 'player_name';

    /**
     * Blocca in modo esplicito ogni azione di scrittura del RelationManager,
     * a prescindere dalla pagina (view o edit) che lo ospita.
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Il tabellino compare solo per le gare che ne hanno uno: per un'amichevole
     * inserita a mano la scheda resterebbe con una tab vuota e inspiegabile.
     * L'autorizzazione di base (viewAny su GamePlayerStat) resta quella di
     * Filament.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && $ownerRecord->playerStats()->exists();
    }

    /**
     * Nessun form: le righe non si creano né si modificano dal CMS.
     */
    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['team', 'player']))
            ->columns([
                Tables\Columns\TextColumn::make('jersey_number')
                    ->label('N°')
                    ->alignCenter()
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('player_name')
                    ->label('Atleta')
                    ->searchable()
                    ->sortable()
                    // Solo le nostre atlete hanno un'anagrafica: per le
                    // avversarie `player_id` è null e resta il solo nome.
                    ->weight(fn (GamePlayerStat $record) => $record->player_id !== null ? FontWeight::Medium : null)
                    ->url(fn (GamePlayerStat $record) => $record->player_id !== null && PlayerResource::canAccess()
                        ? PlayerResource::getUrl('edit', ['record' => $record->player_id])
                        : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('role_flags')
                    ->label('Ruolo')
                    ->badge()
                    ->getStateUsing(fn (GamePlayerStat $record) => array_values(array_filter([
                        $record->is_captain ? 'Capitano' : null,
                        $record->is_libero ? 'Libero' : null,
                    ])))
                    ->color(fn (string $state): string => $state === 'Capitano' ? 'warning' : 'info'),

                Tables\Columns\TextColumn::make('team.name')
                    ->label('Squadra')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sets_played')
                    ->label('Set')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('points_total')
                    ->label('Punti')
                    ->alignCenter()
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                Tables\Columns\TextColumn::make('attack_points')
                    ->label('Attacco')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('block_points')
                    ->label('Muri')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('serve_points')
                    ->label('Ace')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('errors_total')
                    ->label('Errori')
                    ->alignCenter()
                    ->color('danger')
                    ->tooltip('Errori in battuta + attacco + ricezione')
                    ->getStateUsing(fn (GamePlayerStat $record) => $record->serve_errors
                        + $record->attack_errors
                        + $record->reception_errors),

                Tables\Columns\TextColumn::make('serve_errors')
                    ->label('Err. batt.')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('attack_errors')
                    ->label('Err. att.')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('attack_blocked')
                    ->label('Att. murati')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('attack_total')
                    ->label('Att. tot.')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('attack_pct')
                    ->label('Att. %')
                    ->alignCenter()
                    ->suffix('%')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reception_errors')
                    ->label('Err. ric.')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reception_total')
                    ->label('Ric. tot.')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reception_positive_pct')
                    ->label('Ric. pos. %')
                    ->alignCenter()
                    ->suffix('%')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reception_perfect_pct')
                    ->label('Ric. perf. %')
                    ->alignCenter()
                    ->suffix('%')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('points_break')
                    ->label('Break point')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('points_win_loss')
                    ->label('W-L')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Le due squadre restano separate: senza raggruppamento il
            // tabellino sarebbe un elenco unico di 28 nomi.
            ->groups([
                Tables\Grouping\Group::make('team.name')
                    ->label('Squadra')
                    ->collapsible(),
            ])
            ->defaultGroup('team.name')
            ->defaultSort('jersey_number')
            // Un tabellino sta in una schermata: la paginazione spezzerebbe i gruppi.
            ->paginated(false)
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('Tabellino non disponibile')
            ->emptyStateDescription(
                'Le statistiche arrivano dal referto pubblicato dalla Lega e non si '
                .'inseriscono a mano: compaiono qui dopo la sincronizzazione dei tabellini.'
            );
    }
}

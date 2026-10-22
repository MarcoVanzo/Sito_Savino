<?php

namespace App\Filament\Resources\TeamResource\RelationManagers;

use App\Filament\Resources\PlayerStatResource;
use App\Models\PlayerStat;
use App\Models\Season;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

/**
 * Confronto tra le atlete di una squadra in una stagione.
 *
 * Scelto un RelationManager e non una pagina dedicata: il confronto ha senso
 * solo dentro una squadra, e la squadra è già il record che si sta guardando.
 * Una pagina a sé avrebbe richiesto una voce di menu in più e un selettore di
 * squadra che qui è implicito; per di più il RelationManager eredita gratis
 * ordinamento, colonne attivabili e — cosa che pesa — l'autorizzazione su
 * PlayerStat, senza policy nuove da scrivere.
 *
 * La relazione NON esiste sul model `Team` e viene costruita qui in
 * `getRelationship()`: `player_stats.team_id` c'è già (i totali sono per
 * squadra, non solo per stagione), e per una tabella di sola lettura una
 * relazione dinamica basta. Se un domani servisse anche altrove, va promossa a
 * metodo `playerStats()` su Team e questo override si toglie.
 */
class SeasonStatsRelationManager extends RelationManager
{
    /**
     * Nome puramente nominale: `getRelationship()` e `canViewForRecord()` sono
     * entrambi sovrascritti, quindi non viene mai risolto sul model.
     */
    protected static string $relationship = 'playerStats';

    protected static ?string $title = 'Statistiche stagione';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-chart-bar';

    protected static ?string $recordTitleAttribute = 'id';

    public function getRelationship(): Relation|Builder
    {
        return $this->getOwnerRecord()->hasMany(PlayerStat::class, 'team_id');
    }

    /**
     * Le avversarie importate dalla Lega non hanno atlete in anagrafica: la tab
     * resterebbe sempre vuota. L'autorizzazione resta quella di PlayerStat.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) $ownerRecord->is_internal
            && Gate::allows('viewAny', PlayerStat::class);
    }

    /**
     * Blocca ogni scrittura dal RelationManager: le righe importate non vanno
     * toccate, e quelle manuali si modificano nella loro risorsa, dove il form
     * completo e i controlli sull'origine del dato sono già scritti.
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Nessun form: qui non si crea né si modifica.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['player', 'season']))
            ->columns([
                // Senza la colonna "Squadra": qui è costante per definizione.
                ...PlayerStatResource::identityColumns(withTeam: false),
                ...PlayerStatResource::workloadColumns(),
                ...PlayerStatResource::fundamentalColumnGroups(),
                PlayerStatResource::syncColumn()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('points', 'desc')
            ->filters([
                SelectFilter::make('season_id')
                    ->label('Stagione')
                    ->relationship('season', 'name')
                    // Senza un default si sommerebbero visivamente più stagioni
                    // della stessa atleta, e il confronto perderebbe senso.
                    ->default(Season::query()->where('is_current', true)->value('id')),
            ])
            ->persistFiltersInSession()
            // Una rosa sta in una schermata: paginare complicherebbe il confronto.
            ->paginated(false)
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('Dettaglio')
                    ->icon('heroicon-m-eye')
                    ->url(fn (PlayerStat $record): string => PlayerStatResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (PlayerStat $record): bool => PlayerStatResource::canView($record)),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-chart-bar')
            ->emptyStateHeading('Nessuna statistica per questa stagione')
            ->emptyStateDescription(
                'I totali si ricostruiscono dai tabellini della Lega a ogni sincronizzazione. '
                .'Per le squadre giovanili, che non hanno tabellini pubblicati, si inseriscono a '
                .'mano dalla sezione "Statistiche atlete".'
            );
    }
}

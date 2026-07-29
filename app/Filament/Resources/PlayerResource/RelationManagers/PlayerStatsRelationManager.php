<?php

namespace App\Filament\Resources\PlayerResource\RelationManagers;

use App\Filament\Resources\PlayerStatResource;
use App\Models\PlayerStat;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Storico dell'atleta: una riga per stagione e squadra.
 *
 * Era una tabella di cinque numeri con un form di modifica. Due cose l'hanno
 * resa inservibile così com'era:
 *
 * - la colonna "Attacchi" mostrava `attacks`, che ora contiene gli attacchi
 *   TENTATI e non più i punti realizzati (quelli sono in `attack_points`):
 *   l'etichetta era diventata semplicemente falsa;
 * - le righe importate dalla Lega si ricostruiscono a ogni sincronizzazione,
 *   quindi il form di modifica prometteva una persistenza inesistente.
 *
 * Ora è di sola lettura e la scrittura vive tutta in PlayerStatResource, dove
 * il form completo e il vincolo sull'origine del dato sono già scritti.
 */
class PlayerStatsRelationManager extends RelationManager
{
    protected static string $relationship = 'stats';

    protected static ?string $title = 'Statistiche';

    protected static ?string $icon = 'heroicon-o-chart-bar';

    protected static ?string $recordTitleAttribute = 'id';

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Nessun form: qui non si crea né si modifica.
     */
    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['season', 'team']))
            ->columns([
                Tables\Columns\TextColumn::make('season.name')
                    ->label('Stagione')
                    ->sortable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Squadra')
                    ->placeholder('—')
                    ->sortable(),
                ...PlayerStatResource::workloadColumns(),
                ...PlayerStatResource::fundamentalColumnGroups(),
                PlayerStatResource::syncColumn()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Lo storico si legge dalla stagione più recente all'indietro.
            ->defaultSort('season_id', 'desc')
            ->paginated(false)
            ->headerActions([
                Tables\Actions\Action::make('create_manual')
                    ->label('Nuova riga manuale')
                    ->icon('heroicon-m-plus')
                    ->url(fn (): string => PlayerStatResource::getUrl('create'))
                    ->visible(fn (): bool => PlayerStatResource::canCreate()),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Dettaglio')
                    ->icon('heroicon-m-eye')
                    ->url(fn (PlayerStat $record): string => PlayerStatResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (PlayerStat $record): bool => PlayerStatResource::canView($record)),
            ])
            ->bulkActions([])
            ->emptyStateIcon('heroicon-o-chart-bar')
            ->emptyStateHeading('Nessuna statistica')
            ->emptyStateDescription(
                'I totali si ricostruiscono dai tabellini della Lega a ogni sincronizzazione. '
                .'Per le squadre giovanili, che non hanno tabellini pubblicati, si inseriscono a mano.'
            );
    }
}

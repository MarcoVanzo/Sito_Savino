<?php

namespace App\Filament\Resources;

use App\Enums\CodiceDelRedirect;
use App\Filament\Resources\IndirizzoDaMappareResource\Pages\ListIndirizziDaMappare;
use App\Models\IndirizzoDaMappare;
use App\Models\Reindirizzamento;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * I vecchi indirizzi ancora richiesti che non arrivano a un contenuto: 404 in
 * stile WordPress e visite alle rotte generiche di `legacy.php`. Da qui si
 * trasformano in redirect; quelli che non tornano da sei mesi spariscono da
 * soli (`model:prune`).
 */
class IndirizzoDaMappareResource extends Resource
{
    protected static ?string $model = IndirizzoDaMappare::class;

    protected static ?string $recordTitleAttribute = 'percorso';

    protected static ?string $modelLabel = '404 da mappare';

    protected static ?string $pluralModelLabel = '404 da mappare';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Amministrazione';

    protected static ?int $navigationSort = 91;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('percorso')
                    ->label('Vecchio indirizzo')
                    ->searchable()
                    ->limit(80),
                TextColumn::make('origine')
                    ->label('Oggi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === IndirizzoDaMappare::ORIGINE_LEGACY
                        ? 'pagina generica'
                        : '404')
                    ->color(fn (string $state): string => $state === IndirizzoDaMappare::ORIGINE_LEGACY ? 'warning' : 'danger'),
                TextColumn::make('hits')
                    ->label('Richieste')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_hit_at')
                    ->label('Ultima richiesta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('origine')
                    ->label('Oggi')
                    ->options([
                        IndirizzoDaMappare::ORIGINE_404 => '404',
                        IndirizzoDaMappare::ORIGINE_LEGACY => 'Pagina generica',
                    ]),
            ])
            ->defaultSort('hits', 'desc')
            ->recordActions([
                Action::make('creaRedirect')
                    ->label('Crea redirect')
                    ->icon('heroicon-o-arrow-uturn-right')
                    ->visible(fn (): bool => Gate::allows('create', Reindirizzamento::class))
                    ->fillForm(fn (IndirizzoDaMappare $record): array => [
                        'from_path' => $record->percorso,
                        'status_code' => CodiceDelRedirect::Permanente->value,
                    ])
                    ->schema(ReindirizzamentoResource::campi())
                    ->action(function (array $data): void {
                        // Il salvataggio toglie anche la riga da qui.
                        Reindirizzamento::create($data);

                        Notification::make()->title('Redirect creato')->success()->send();
                    }),
                DeleteAction::make()
                    ->label('Ignora'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Ignora'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndirizziDaMappare::route('/'),
        ];
    }
}

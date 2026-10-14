<?php

namespace App\Filament\Resources;

use App\Enums\CodiceDelRedirect;
use App\Filament\Resources\IndirizzoDaMappareResource\Pages;
use App\Models\IndirizzoDaMappare;
use App\Models\Reindirizzamento;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
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

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationGroup = 'Amministrazione';

    protected static ?int $navigationSort = 91;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('percorso')
                    ->label('Vecchio indirizzo')
                    ->searchable()
                    ->limit(80),
                Tables\Columns\TextColumn::make('origine')
                    ->label('Oggi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === IndirizzoDaMappare::ORIGINE_LEGACY
                        ? 'pagina generica'
                        : '404')
                    ->color(fn (string $state): string => $state === IndirizzoDaMappare::ORIGINE_LEGACY ? 'warning' : 'danger'),
                Tables\Columns\TextColumn::make('hits')
                    ->label('Richieste')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_hit_at')
                    ->label('Ultima richiesta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('origine')
                    ->label('Oggi')
                    ->options([
                        IndirizzoDaMappare::ORIGINE_404 => '404',
                        IndirizzoDaMappare::ORIGINE_LEGACY => 'Pagina generica',
                    ]),
            ])
            ->defaultSort('hits', 'desc')
            ->actions([
                Tables\Actions\Action::make('creaRedirect')
                    ->label('Crea redirect')
                    ->icon('heroicon-o-arrow-uturn-right')
                    ->visible(fn (): bool => Gate::allows('create', Reindirizzamento::class))
                    ->fillForm(fn (IndirizzoDaMappare $record): array => [
                        'from_path' => $record->percorso,
                        'status_code' => CodiceDelRedirect::Permanente->value,
                    ])
                    ->form(ReindirizzamentoResource::campi())
                    ->action(function (array $data): void {
                        // Il salvataggio toglie anche la riga da qui.
                        Reindirizzamento::create($data);

                        Notification::make()->title('Redirect creato')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Ignora'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Ignora'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIndirizziDaMappare::route('/'),
        ];
    }
}

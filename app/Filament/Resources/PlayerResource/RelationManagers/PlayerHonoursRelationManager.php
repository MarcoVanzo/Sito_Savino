<?php

namespace App\Filament\Resources\PlayerResource\RelationManagers;

use App\Enums\HonourMedal;
use App\Enums\PlayerHonourCategory;
use App\Filament\Actions\ImportPalmaresAction;
use App\Models\Player;
use App\Models\PlayerHonour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Palmarès dell'atleta: la tabella che la redazione usa dopo l'importazione.
 *
 * Due regole rendono convivibili importazione e lavoro manuale:
 *
 * - qualunque riga si crei o si modifichi qui diventa `manual`, e da quel
 *   momento l'importazione non la tocca più;
 * - togliere una riga arrivata da Wikipedia non la cancella: la nasconde e la
 *   marca `manual`. Cancellarla davvero non servirebbe a niente, perché la
 *   prima reimportazione la rimetterebbe in pagina.
 */
class PlayerHonoursRelationManager extends RelationManager
{
    protected static string $relationship = 'honours';

    protected static ?string $title = 'Palmarès';

    protected static ?string $icon = 'heroicon-o-trophy';

    protected static ?string $recordTitleAttribute = 'edition';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category')
                    ->label('Tipo')
                    ->options(PlayerHonourCategory::class)
                    ->default(PlayerHonourCategory::Club->value)
                    ->required(),
                Forms\Components\Select::make('medal')
                    ->label('Medaglia')
                    ->options(HonourMedal::class)
                    ->placeholder('Nessuna — titolo vinto')
                    ->helperText('Da valorizzare solo per le medaglie in nazionale.'),
                Forms\Components\TextInput::make('competition.it')
                    ->label('Competizione (IT)')
                    ->required()
                    ->maxLength(180),
                Forms\Components\TextInput::make('competition.en')
                    ->label('Competizione (EN)')
                    ->helperText('Lasciando vuoto, in inglese si vede il testo italiano.')
                    ->maxLength(180),
                Forms\Components\TextInput::make('edition')
                    ->label('Edizione')
                    ->placeholder('2020-21, Rio de Janeiro 2016…')
                    ->maxLength(160),
                Forms\Components\TextInput::make('year')
                    ->label('Anno')
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue(2100)
                    ->helperText('Serve solo a ordinare cronologicamente.'),
                Forms\Components\TextInput::make('note.it')
                    ->label('Premio / nota (IT)')
                    ->placeholder('Miglior palleggiatrice')
                    ->maxLength(180),
                Forms\Components\TextInput::make('note.en')
                    ->label('Premio / nota (EN)')
                    ->maxLength(180),
                Forms\Components\Toggle::make('is_visible')
                    ->label('Visibile sul sito')
                    ->default(true),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('edition')
            // La relazione `honours` arriva già ordinata per la pubblicazione:
            // senza `reorder()` quell'ordine avrebbe la precedenza su quello
            // scelto qui e il riordino a trascinamento non si vedrebbe.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->reorder())
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('category')
                    ->label('Tipo')
                    ->badge(),
                Tables\Columns\TextColumn::make('competition')
                    ->label('Competizione')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('edition')
                    ->label('Edizione'),
                Tables\Columns\TextColumn::make('medal')
                    ->label('Medaglia')
                    ->badge()
                    ->placeholder('titolo'),
                Tables\Columns\TextColumn::make('note')
                    ->label('Premio / nota')
                    ->wrap()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origine')
                    ->badge()
                    ->color(fn (string $state): string => $state === PlayerHonour::SOURCE_WIKIPEDIA ? 'gray' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === PlayerHonour::SOURCE_WIKIPEDIA ? 'Wikipedia' : 'Redazione'),
                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Online')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Tipo')
                    ->options(PlayerHonourCategory::class),
            ])
            ->headerActions([
                ImportPalmaresAction::make($this->player()),
                Tables\Actions\CreateAction::make()
                    ->label('Aggiungi riga')
                    ->mutateFormDataUsing(static::markAsManual(...)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(static::splitTranslations(...))
                    ->mutateFormDataUsing(static::markAsManual(...)),
                Tables\Actions\Action::make('hide')
                    ->label('Rimuovi')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Rimuovi dal palmarès')
                    ->modalDescription('La riga sparisce dal sito. Se arrivava da Wikipedia resta in archivio nascosta, così la prossima importazione non la rimette online.')
                    ->visible(fn (PlayerHonour $record): bool => (bool) $record->is_visible)
                    ->action(function (PlayerHonour $record): void {
                        if ($record->source === PlayerHonour::SOURCE_WIKIPEDIA) {
                            $record->update(['is_visible' => false, 'source' => PlayerHonour::SOURCE_MANUAL]);

                            return;
                        }

                        $record->delete();
                    }),
                Tables\Actions\Action::make('restore')
                    ->label('Ripristina')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (PlayerHonour $record): bool => ! $record->is_visible)
                    ->action(fn (PlayerHonour $record) => $record->update(['is_visible' => true])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nessun palmarès')
            ->emptyStateDescription('Usa "Crea palmarès" per leggerlo dalla voce di Wikipedia dell\'atleta, oppure aggiungi le righe a mano.');
    }

    /**
     * Il record proprietario tipizzato: il palmarès è dell'atleta, e questo
     * relation manager non è montabile su nient'altro.
     */
    private function player(): Player
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Player) {
            throw new InvalidArgumentException('Il palmarès si gestisce solo sull\'anagrafica di un\'atleta.');
        }

        return $owner;
    }

    /**
     * Il form espone le due lingue come campi separati; il modello vuole
     * l'array per lingua. Qui si rimette insieme, scartando l'inglese vuoto
     * (che altrimenti coprirebbe il ripiego sull'italiano con una stringa
     * vuota) e marcando la riga come lavoro di redazione.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function markAsManual(array $data): array
    {
        foreach (['competition', 'note'] as $field) {
            if (! is_array($data[$field] ?? null)) {
                continue;
            }

            $data[$field] = array_filter(
                $data[$field],
                static fn ($value): bool => is_string($value) && trim($value) !== ''
            );

            if ($data[$field] === []) {
                $data[$field] = null;
            }
        }

        $data['source'] = PlayerHonour::SOURCE_MANUAL;

        return $data;
    }

    /**
     * In lettura il modello restituisce la traduzione della lingua attiva: per
     * riempire i due campi servono tutte.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function splitTranslations(array $data, PlayerHonour $record): array
    {
        $data['competition'] = $record->getTranslations('competition');
        $data['note'] = $record->getTranslations('note');

        return $data;
    }
}

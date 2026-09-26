<?php

namespace App\Filament\Resources;

use App\Enums\EtichettaProdotto;
use App\Enums\ProductType;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Product;
use App\Support\EtichetteDelProdotto;
use App\Support\GuidaTaglie;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?string $model = Product::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Prodotto';

    protected static ?string $pluralModelLabel = 'Prodotti';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Catalogo Prodotti';

    protected static ?string $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'shop/products';

    /**
     * Dal modulo alla colonna: con le etichette automatiche `etichette` torna
     * nulla. La lista nascosta non viene deidratata, quindi senza questo
     * passaggio riaccendere "Automatiche" lascerebbe in archivio l'ultima
     * scelta a mano.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function etichetteDalModulo(array $data): array
    {
        if (array_key_exists('etichette_automatiche', $data)) {
            $data['etichette'] = $data['etichette_automatiche']
                ? null
                : array_values($data['etichette'] ?? []);
        }

        unset($data['etichette_automatiche']);

        return $data;
    }

    /**
     * Un articolo indossato o autografato non si salva senza lo stato in
     * italiano: le condizioni di vendita lo vendono «nello stato descritto
     * nella scheda», e l'inglese ripiega sull'italiano finche' non e' tradotto.
     *
     * Non e' un `->required()` sul campo perche' il campo e' tradotto: al
     * salvataggio il plugin translatable rivalida il modulo con i dati di ogni
     * altra lingua visitata e, se la validazione fallisce, scarta quella
     * lingua senza dirlo (EditRecord\Concerns\Translatable::handleRecordUpdate).
     * Con un obbligo sul campo, uno stato non ancora tradotto in inglese
     * avrebbe buttato in silenzio anche nome e descrizione inglesi appena
     * scritti. Qui si guarda solo l'italiano, dovunque stia: nel modulo, nei
     * dati della lingua lasciata o nell'archivio.
     *
     * Da chiamare in beforeCreate/beforeSave delle pagine del prodotto.
     */
    public static function verificaStatoDellArticolo(Pages\CreateProduct|Pages\EditProduct $pagina): void
    {
        if (! ($pagina->data['usato_o_autografato'] ?? false)) {
            return;
        }

        $italiano = config('app.fallback_locale');
        $inItaliano = $pagina->activeLocale === $italiano;

        $stato = $inItaliano
            ? ($pagina->data['stato_articolo'] ?? null)
            : ($pagina->otherLocaleData[$italiano]['stato_articolo']
                ?? ($pagina->record instanceof Product ? $pagina->record->getTranslation('stato_articolo', $italiano, false) : null));

        if (trim((string) $stato) !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'data.stato_articolo' => $inItaliano
                ? 'Obbligatorio per un articolo indossato o autografato: le condizioni di vendita lo vendono nello stato descritto qui.'
                : 'Obbligatorio per un articolo indossato o autografato: compilalo prima in italiano (l\'inglese ripiega sull\'italiano finché non lo traduci).',
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Principali')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Prodotto')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        Forms\Components\TextInput::make('slug')
                            ->label('URL Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('product_category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->label('Nome Categoria')->required(),
                                Forms\Components\TextInput::make('slug')->label('Slug')->required(),
                            ]),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Visibile nello Shop')
                            ->required()
                            ->default(true),
                        Forms\Components\Select::make('type')
                            ->label('Tipo Prodotto')
                            ->options(collect(ProductType::cases())->filter(fn ($t) => $t !== ProductType::Auction)->mapWithKeys(fn ($t) => [$t->value => $t->getLabel()]))
                            ->required()
                            ->default(ProductType::Simple),
                        Forms\Components\Textarea::make('short_description')
                            ->label('Descrizione Breve')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Mostrata nella lista prodotti e nella card'),
                        Forms\Components\RichEditor::make('description')
                            ->label('Descrizione Prodotto')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'orderedList',
                                'bulletList',
                                'h2',
                                'h3',
                                'blockquote',
                                'redo',
                                'undo',
                            ])
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Prezzo e Inventario')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Prezzo (€)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€'),
                        Forms\Components\TextInput::make('stock')
                            ->label('Stock Attuale')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->disabled(fn (string $context): bool => $context === 'edit')
                            ->dehydrated(fn (string $context): bool => $context !== 'edit')
                            // Per un prodotto con varianti la colonna `stock`
                            // non la aggiorna nessuno: la giacenza sta sulle
                            // taglie. Mostrarla lasciava in pagina il 56
                            // ereditato da WooCommerce mentre il sito, che
                            // somma le varianti (Product::availableStock),
                            // ne contava 19.
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, ?Product $record): void {
                                if ($record?->type === ProductType::Variable) {
                                    $component->state($record->availableStock());
                                }
                            })
                            ->helperText(fn (string $context, ?Product $record): ?string => match (true) {
                                $context !== 'edit' => null,
                                $record?->type === ProductType::Variable => 'Somma delle giacenze delle varianti. Si modifica dalla tab "Varianti" o dai Movimenti Magazzino.',
                                default => 'Gestito dai Movimenti Magazzino. Modifica tramite la sezione dedicata.',
                            }),
                        Forms\Components\TextInput::make('sku')
                            ->label('Codice (SKU)')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('sale_price')
                            ->label('Prezzo Scontato (€)')
                            ->numeric()
                            ->prefix('€')
                            ->nullable()
                            // `->lte('price')` e non `->rule('lte:price')`: la
                            // regola scritta a mano cerca un campo `price` alla
                            // radice dei dati validati, che stanno tutti sotto
                            // `data.` — non lo trovava mai e falliva sempre,
                            // impedendo di salvare il prodotto intero (sconto,
                            // SKU, peso e date comprese).
                            ->lte('price')
                            ->helperText('Sul sito il prezzo barrato è il più basso praticato nei 30 giorni prima dello sconto (obbligo di legge), non il listino: un prodotto nato già scontato si vende al prezzo scontato senza barrato. Per codici promozionali al checkout, vai a Codici Promozionali nel menu Shop.'),
                        Forms\Components\DateTimePicker::make('sale_start')
                            ->label('Inizio Sconto'),
                        Forms\Components\DateTimePicker::make('sale_end')
                            ->label('Fine Sconto'),
                        Forms\Components\TextInput::make('weight')
                            ->label('Peso (kg)')
                            ->numeric()
                            ->nullable()
                            ->suffix('kg')
                            ->helperText('Usato dalle fasce di peso della spedizione. Vuoto: vale il peso di ripiego delle Impostazioni Shop.'),
                        Forms\Components\Select::make('size_guide')
                            ->label('Guida alle taglie')
                            // I PDF sono quelli caricati in "Guida Taglie &
                            // Contatti": qui si sceglie quale mostrare, non
                            // se ne carica uno nuovo.
                            ->options(GuidaTaglie::opzioni())
                            ->placeholder('Quella generale (tutti i documenti)')
                            ->helperText('La voce sotto le taglie, nella scheda del prodotto. Scegli "Nessuna guida" per i prodotti per cui non ne esiste una.'),
                    ])->columns(2),

                Forms\Components\Section::make('Etichette in vetrina')
                    ->description('Le scritte sulla foto del prodotto, nella griglia dello shop e nella scheda. Al massimo due: la prima va a sinistra, la seconda a destra.')
                    ->schema([
                        // Non e' una colonna: dice se `etichette` resta nulla
                        // (automatiche). Si traduce in ProductResource::etichetteDalModulo.
                        Forms\Components\Toggle::make('etichette_automatiche')
                            ->label('Automatiche')
                            ->helperText('"Nuovo" nei primi 30 giorni e "In offerta" durante lo sconto. Spegnila per sceglierle tu.')
                            ->default(true)
                            ->live()
                            ->afterStateHydrated(function (Forms\Components\Toggle $component, ?Product $record): void {
                                if ($record !== null) {
                                    $component->state($record->etichette === null);
                                }
                            }),
                        Forms\Components\CheckboxList::make('etichette')
                            ->label('Etichette da mostrare')
                            ->options(collect(EtichettaProdotto::cases())->mapWithKeys(fn (EtichettaProdotto $e) => [$e->value => $e->getLabel()])->all())
                            ->descriptions(collect(EtichettaProdotto::cases())
                                ->filter(fn (EtichettaProdotto $e) => $e->descrizioneNelPannello() !== null)
                                ->mapWithKeys(fn (EtichettaProdotto $e) => [$e->value => $e->descrizioneNelPannello()])
                                ->all())
                            ->maxItems(EtichetteDelProdotto::MASSIMO)
                            ->columns(2)
                            // "Nuovo" scelto a mano non scade: EtichetteDelProdotto lo
                            // tratta come "Hot sales". Va detto qui, o chi lo spunta
                            // si aspetta che sparisca da solo come quello automatico.
                            ->helperText('Nessuna selezionata: il prodotto non mostra etichette. "Nuovo" scelto qui resta finché non lo togli: quello automatico, invece, sparisce da solo dopo 30 giorni.')
                            ->hidden(fn (Forms\Get $get): bool => (bool) $get('etichette_automatiche')),
                    ]),

                Forms\Components\Section::make('Personalizzazione')
                    ->description('Un\'aggiunta facoltativa che il cliente sceglie nella scheda, di solito la firma della giocatrice. Non tocca le giacenze: il pezzo in magazzino resta lo stesso.')
                    ->schema([
                        Forms\Components\TextInput::make('personalizzazione_nome')
                            ->label('Nome dell\'aggiunta')
                            ->placeholder('Firma della giocatrice')
                            ->maxLength(120)
                            ->helperText('Vuoto: il prodotto non la offre. Si attiva dal nome in italiano; in inglese si traduce cambiando lingua in alto.'),
                        Forms\Components\TextInput::make('personalizzazione_prezzo')
                            ->label('Supplemento (€)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            // Svuotato arriverebbe come null a una colonna NOT NULL e
                            // il prodotto non si salverebbe: vuoto vale "inclusa".
                            ->dehydrateStateUsing(fn ($state) => blank($state) ? 0 : $state)
                            ->prefix('€')
                            ->helperText('Si somma al prezzo del pezzo. 0: inclusa nel prezzo.'),
                    ])->columns(2),

                Forms\Components\Section::make('Stato dell\'articolo')
                    ->description('Per maglie indossate in gara e articoli autografati. Le condizioni di vendita li vendono «nello stato descritto nella scheda»: il testo compare accanto al prezzo e viene copiato nell\'ordine al momento dell\'acquisto.')
                    ->schema([
                        Forms\Components\Toggle::make('usato_o_autografato')
                            ->label('Articolo indossato o autografato')
                            ->helperText('Accesa, lo stato è obbligatorio.')
                            ->default(false)
                            ->live(),
                        // Sempre visibile: un campo nascosto non viene
                        // deidratato (CLAUDE.md §14). L'obbligo lo verifica
                        // verificaStatoDellArticolo, non ->required(): vedi li'.
                        Forms\Components\Textarea::make('stato_articolo')
                            ->label('Stato dell\'articolo')
                            ->rows(3)
                            ->maxLength(2000)
                            ->markAsRequired(fn (Forms\Get $get): bool => (bool) $get('usato_o_autografato'))
                            ->placeholder('es. indossata in gara il 12/10/2025 contro Conegliano, segni di gioco sul fronte, non lavata, autografo originale sul numero')
                            ->helperText('Descrivi com\'è davvero: quando è stata indossata, segni di gioco, se è lavata, autografo originale e dove. In inglese si traduce cambiando lingua in alto; finché manca, il sito mostra l\'italiano.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Articoli collegati')
                    ->description('Compaiono in fondo alla scheda, sotto "Ti potrebbe interessare anche". Lasciando vuoto, il sito continua a proporre da sé quattro articoli della stessa categoria.')
                    ->schema([
                        Forms\Components\Select::make('relatedProducts')
                            ->label('Prodotti da mostrare')
                            ->relationship(
                                name: 'relatedProducts',
                                titleAttribute: 'name',
                                // Niente aste (non sono in vendita) e niente
                                // se stesso, che in vetrina si mostrerebbe
                                // sotto la propria scheda.
                                modifyQueryUsing: function (Builder $query, Forms\Components\Select $component): Builder {
                                    $query->where('type', '!=', ProductType::Auction);

                                    $record = $component->getRecord();

                                    if ($record instanceof Product) {
                                        $query->whereKeyNot($record->getKey());
                                    }

                                    return $query;
                                },
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Il collegamento vale in un verso solo: per vederli accostati anche al contrario, aggiungi questo prodotto anche nella scheda dell\'altro.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Galleria Immagini')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('images')
                            ->label('Foto Prodotto')
                            ->collection('images')
                            ->multiple()
                            ->image()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Varianti (Opzionale)')
                    ->schema([
                        // In creazione: Repeater inline per setup iniziale (stock disabilitato, verrà gestito dai Movimenti Magazzino)
                        Forms\Components\Repeater::make('variants')
                            ->defaultItems(0)
                            ->label('Aggiungi Varianti')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('size')
                                    ->label('Taglia (es. S, M, L)'),
                                Forms\Components\TextInput::make('color')
                                    ->label('Colore'),
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU Variante')
                                    ->required()
                                    ->unique(table: 'product_variants', column: 'sku'),
                                Forms\Components\TextInput::make('stock')
                                    ->label('Stock Iniziale')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Imposta lo stock iniziale. Dopo la creazione, usa la tab "Varianti" per gestirlo.'),
                                Forms\Components\TextInput::make('price_modifier')
                                    ->numeric()
                                    ->default(0)
                                    ->label('Variazione Prezzo (€)'),
                            ])
                            ->columns(5)
                            ->columnSpanFull()
                            ->visible(fn (string $operation): bool => $operation === 'create'),

                        // In modifica: messaggio che indica di usare il RelationManager
                        Forms\Components\Placeholder::make('variants_info')
                            ->label('')
                            ->content('Le varianti di questo prodotto sono gestite dalla tab "Varianti" in fondo alla pagina. Da lì puoi aggiungere, modificare ed eliminare varianti e gestire lo stock.')
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('images')
                    ->checkFileExistence(false)
                    ->conversion('thumb')
                    ->label('')
                    ->collection('images')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Prodotto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Scontato')
                    ->money('EUR')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Giacenza')
                    // Come nel modulo: per i prodotti a varianti la colonna
                    // del prodotto non dice nulla, conta la somma delle
                    // taglie (precaricata da getEloquentQuery).
                    // Niente ordinamento: la colonna del prodotto e la somma
                    // delle taglie sono due numeri diversi, e ordinare per la
                    // prima mostrando la seconda confonde e basta.
                    ->state(fn (Product $record): int => $record->availableStock())
                    ->numeric(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Attivo'),
                Tables\Filters\SelectFilter::make('product_category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name'),
                Tables\Filters\Filter::make('on_sale')
                    ->label('In Saldo')
                    ->query(fn (Builder $query) => $query->whereNotNull('sale_price')->where('sale_price', '>', 0)),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('Anteprima')
                    ->url(fn ($record) => route('shop.product', ['product' => $record->slug]))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplica')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplica prodotto')
                    ->modalDescription('Verrà creata una copia del prodotto con stock azzerato e slug modificato.')
                    ->action(function (Product $record): void {
                        $newProduct = $record->replicate(['stock']);
                        $newProduct->name = $record->name.' (Copia)';
                        $newProduct->slug = $record->slug.'-copia-'.now()->timestamp;
                        $newProduct->stock = 0;
                        $newProduct->is_active = false;
                        $newProduct->save();

                        // Copy media
                        foreach ($record->getMedia('images') as $media) {
                            $media->copy($newProduct, 'images');
                        }

                        // Copy variants (with unique SKU suffix)
                        $suffix = '-copia-'.now()->timestamp;
                        foreach ($record->variants as $variant) {
                            $newVariant = $variant->replicate();
                            $newVariant->product_id = $newProduct->id;
                            $newVariant->sku = $variant->sku ? $variant->sku.$suffix : null;
                            $newVariant->stock = 0;
                            $newVariant->save();
                        }

                        Notification::make()
                            ->title('Prodotto duplicato')
                            ->body("Creata copia: {$newProduct->name}")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
                Tables\Actions\BulkAction::make('apply_discount')
                    ->label('Applica Sconto')
                    ->icon('heroicon-o-tag')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('sale_price')->label('Prezzo Scontato (€)')->numeric()->required()->prefix('€'),
                        Forms\Components\DateTimePicker::make('sale_start')->label('Inizio Sconto'),
                        Forms\Components\DateTimePicker::make('sale_end')->label('Fine Sconto'),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $records->each(fn ($product) => $product->update([
                            'sale_price' => $data['sale_price'],
                            'sale_start' => $data['sale_start'] ?? null,
                            'sale_end' => $data['sale_end'] ?? null,
                        ]));
                        Notification::make()->title('Sconto applicato a '.$records->count().' prodotti')->success()->send();
                        // La cache pubblica dello shop la invalida CacheInvalidationObserver
                        // (registrato su Product): non duplicare qui le chiavi per lingua.
                    })
                    ->deselectRecordsAfterCompletion()
                    ->requiresConfirmation(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['category', 'media'])
            ->withSum('variants', 'stock');
    }
}

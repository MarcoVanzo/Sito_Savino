<?php

namespace App\Filament\Pages;

use App\Enums\StockMovementType;
use App\Filament\Pages\Concerns\RestrictsAccessByRole;
use App\Filament\Widgets\StockMovementTableWidget;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MagazzinoPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use RestrictsAccessByRole;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop Ufficiale';

    protected static ?string $navigationLabel = 'Magazzino';

    protected static ?string $title = 'Magazzino';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'shop/magazzino';

    protected string $view = 'filament.pages.magazzino-page';

    /**
     * Tab attiva: 'inventario' o 'movimenti'.
     */
    public string $activeTab = 'inventario';

    /**
     * Tabella principale: Inventario Prodotti.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->with(['category', 'variants'])
                    ->where('is_active', true)
            )
            ->columns($this->colonne())
            ->defaultSort('stock', 'asc')
            ->filters($this->filtri())
            ->recordActions($this->azioni())
            ->toolbarActions([])
            ->emptyStateHeading('Nessun prodotto trovato')
            ->emptyStateDescription('Non ci sono prodotti attivi nel catalogo.')
            ->poll('30s');
    }

    /**
     * @return array<int, Column>
     */
    private function colonne(): array
    {
        return [
            SpatieMediaLibraryImageColumn::make('images')
                ->checkFileExistence(false)
                ->conversion('thumb')
                ->label('')
                ->collection('images')
                ->square(),

            TextColumn::make('name')
                ->label('Prodotto')
                ->searchable()
                ->sortable(),

            TextColumn::make('category.name')
                ->label('Categoria')
                ->sortable()
                ->placeholder('-'),

            TextColumn::make('sku')
                ->label('SKU')
                ->searchable()
                ->placeholder('-'),

            TextColumn::make('stock')
                ->label('Stock')
                ->numeric()
                ->sortable()
                ->description(function (Product $record): ?string {
                    if ($record->variants->isEmpty()) {
                        return null;
                    }

                    // Mostra breakdown varianti come descrizione
                    return $record->variants
                        ->map(fn ($v) => ($v->size ?: $v->color ?: $v->sku).': '.$v->stock)
                        ->join(' | ');
                }),

            TextColumn::make('stock_status')
                ->label('Stato')
                ->badge()
                ->state(function (Product $record): string {
                    // Per prodotti con varianti, usa il livello peggiore
                    $stockLevel = $record->variants->isNotEmpty()
                        ? $record->variants->min('stock')
                        : $record->stock;

                    if ($stockLevel <= 0) {
                        return 'Esaurito';
                    }
                    if ($stockLevel <= 5) {
                        return 'Basso';
                    }

                    return 'Disponibile';
                })
                ->color(fn (string $state): string => match ($state) {
                    'Esaurito' => 'danger',
                    'Basso' => 'warning',
                    'Disponibile' => 'success',
                    default => 'gray',
                })
                ->icon(fn (string $state): string => match ($state) {
                    'Esaurito' => 'heroicon-o-x-circle',
                    'Basso' => 'heroicon-o-exclamation-triangle',
                    'Disponibile' => 'heroicon-o-check-circle',
                    default => 'heroicon-o-question-mark-circle',
                }),

            TextColumn::make('price')
                ->label('Prezzo')
                ->money('EUR')
                ->sortable(),
        ];
    }

    /**
     * @return array<int, BaseFilter>
     */
    private function filtri(): array
    {
        return [
            SelectFilter::make('stock_status')
                ->label('Stato Stock')
                ->options([
                    'esaurito' => '🔴 Esaurito',
                    'basso' => '🟠 Basso (≤5)',
                    'disponibile' => '🟢 Disponibile',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return match ($data['value'] ?? null) {
                        'esaurito' => $query->where('stock', '<=', 0),
                        'basso' => $query->where('stock', '>', 0)->where('stock', '<=', 5),
                        'disponibile' => $query->where('stock', '>', 5),
                        default => $query,
                    };
                }),

            // Principale o aggiuntiva, come la vetrina e l'elenco Prodotti.
            SelectFilter::make('product_category_id')
                ->label('Categoria')
                ->relationship('category', 'name')
                ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                    ? $query->nelleCategorie([(int) $data['value']])
                    : $query),

            TernaryFilter::make('is_active')
                ->label('Attivo')
                ->default(true),
        ];
    }

    /**
     * Carico e scarico dello stock direttamente dall'elenco.
     *
     * @return array<int, Action>
     */
    private function azioni(): array
    {
        return [
            Action::make('carica_stock')
                ->label('Carica stock')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->modalHeading(fn (Product $record): string => "Carica stock: {$record->name}")
                ->modalSubmitActionLabel('Conferma carico')
                ->schema(function (Product $record): array {
                    $schema = [];

                    // Se il prodotto ha varianti, mostra il selettore
                    if ($record->variants->isNotEmpty()) {
                        $schema[] = Select::make('product_variant_id')
                            ->label('Variante')
                            ->options(
                                $record->variants->mapWithKeys(fn ($v) => [
                                    $v->id => collect([$v->size, $v->color, $v->sku])
                                        ->filter()
                                        ->join(' — ')." (stock: {$v->stock})",
                                ])
                            )
                            ->required();
                    }

                    $schema[] = TextInput::make('quantity')
                        ->label('Quantità da caricare')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(10000)
                        ->default(1)
                        ->helperText('Inserisci la quantità di pezzi da aggiungere al magazzino.');

                    $schema[] = Textarea::make('notes')
                        ->label('Note (opzionale)')
                        ->placeholder('Es. Arrivo merce fornitore, inventario...');

                    return $schema;
                })
                ->action(function (Product $record, array $data): void {
                    $qty = (int) $data['quantity'];
                    $variantId = $data['product_variant_id'] ?? null;

                    // Il StockMovementObserver aggiorna lo stock automaticamente
                    // quando il movimento viene creato — non fare increment manuale
                    StockMovement::create([
                        'product_id' => $record->id,
                        'product_variant_id' => $variantId,
                        'quantity' => $qty,
                        'type' => StockMovementType::Purchase,
                        'notes' => $data['notes'] ?? 'Carico manuale da inventario',
                    ]);

                    Notification::make()
                        ->title('Stock aggiornato')
                        ->body("Aggiunti {$qty} pezzi a \"{$record->name}\".")
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Widget footer: tabella movimenti (lazy loaded dal tab).
     */
    protected function getFooterWidgets(): array
    {
        return [
            StockMovementTableWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    protected static function requiredAbility(): string
    {
        return 'canManageShop';
    }
}

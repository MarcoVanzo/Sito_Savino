<?php

namespace App\Filament\Resources;

use App\Enums\ProductType;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

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
                        Forms\Components\Textarea::make('description')
                            ->label('Descrizione Prodotto')
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
                            ->dehydrated()
                            ->helperText(fn (string $context): ?string => $context === 'edit' ? 'Gestito dai Movimenti Magazzino. Modifica tramite la sezione dedicata.' : null),
                        Forms\Components\TextInput::make('sku')
                            ->label('Codice (SKU)')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('sale_price')
                            ->label('Prezzo Scontato (€)')
                            ->numeric()
                            ->prefix('€')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('sale_start')
                            ->label('Inizio Sconto'),
                        Forms\Components\DateTimePicker::make('sale_end')
                            ->label('Fine Sconto'),
                        Forms\Components\TextInput::make('weight')
                            ->label('Peso (kg)')
                            ->numeric()
                            ->nullable()
                            ->suffix('kg'),
                    ])->columns(2),

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
                        Forms\Components\Repeater::make('variants')
                            ->label('Aggiungi Varianti')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('size')
                                    ->label('Taglia (es. S, M, L)'),
                                Forms\Components\TextInput::make('color')
                                    ->label('Colore'),
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU Variante'),
                                Forms\Components\TextInput::make('stock')
                                    ->label('Stock')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('price_modifier')
                                    ->numeric()
                                    ->default(0)
                                    ->label('Variazione Prezzo (€)'),
                            ])
                            ->columns(5)
                            ->columnSpanFull(),
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
                    ->label('Stock (Base)')
                    ->numeric()
                    ->sortable(),
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
                    ->url(fn ($record) => url('shop/'.($record->slug ?? '')))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                Tables\Actions\EditAction::make(),
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
                        Notification::make()->title('Sconto applicato a ' . $records->count() . ' prodotti')->success()->send();
                        Cache::forget('public:shop:it');
                        Cache::forget('public:shop:en');
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
            ->with(['category', 'media']);
    }
}

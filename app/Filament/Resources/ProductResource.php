<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Prodotto';
    protected static ?string $pluralModelLabel = 'Prodotti';
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Shop';
    protected static ?int $navigationSort = 13;

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
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null),
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
                            ->nullable(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Visibile nello Shop')
                            ->required()
                            ->default(true),
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
                    ])->columns(3),

                Forms\Components\Section::make('Galleria Immagini')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('images')
                            ->label('Foto Prodotto')
                            ->collection('products')
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
                    ->label('')
                    ->collection('products')
                    ->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome Prodotto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR')
                    ->sortable(),
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
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('Anteprima')
                    ->url(fn ($record) => url('shop/' . ($record->slug ?? '')))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
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
            ->with(['category']);
    }
}

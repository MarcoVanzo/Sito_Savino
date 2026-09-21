<?php

namespace App\Filament\Resources;

use App\Enums\CouponType;
use App\Filament\Resources\CouponResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CouponResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Coupon::class;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $modelLabel = 'Coupon';

    protected static ?string $pluralModelLabel = 'Coupon';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Codici Promozionali';

    protected static ?string $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'shop/coupon';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('info_banner')
                    ->label('')
                    ->content('Qui gestisci i codici promozionali che i clienti inseriscono al checkout. Per sconti diretti sui prodotti, modifica il prezzo scontato nel Catalogo Prodotti.')
                    ->columnSpanFull(),
                Forms\Components\Section::make('Codice e Tipo')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Codice Coupon')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Forms\Components\Select::make('type')
                            ->label('Tipo Sconto')
                            ->options(CouponType::class)
                            ->required()
                            ->default(CouponType::Percentage)
                            ->live(),
                        Forms\Components\TextInput::make('value')
                            ->label('Valore')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn (Forms\Get $get) => self::eInPercentuale($get) ? 100 : null)
                            ->suffix(fn (Forms\Get $get) => self::eInPercentuale($get) ? '%' : '€'),
                        Forms\Components\TextInput::make('max_discount')
                            ->label('Sconto Massimo (€)')
                            ->numeric()
                            ->nullable()
                            ->prefix('€')
                            ->helperText('Solo per coupon percentuali')
                            ->visible(fn (Forms\Get $get) => self::eInPercentuale($get)),
                    ])->columns(2),
                Forms\Components\Section::make('Condizioni')
                    ->schema([
                        Forms\Components\TextInput::make('min_order_amount')
                            ->label('Ordine Minimo (€)')
                            ->numeric()
                            ->nullable()
                            ->prefix('€'),
                        Forms\Components\TextInput::make('max_uses')
                            ->label('Utilizzi Totali Max')
                            ->numeric()
                            ->nullable(),
                        Forms\Components\TextInput::make('max_uses_per_user')
                            ->label('Utilizzi Max per Utente')
                            ->numeric()
                            ->default(1),
                    ])->columns(3),
                Forms\Components\Section::make('Prodotti a cui si applica')
                    ->description('Lascia vuoto per applicare il codice a tutto il carrello. Compilando anche solo uno dei due elenchi, lo sconto vale soltanto sugli articoli indicati.')
                    ->schema([
                        Forms\Components\Select::make('products')
                            ->label('Prodotti')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('categories')
                            ->label('Categorie')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            // Le sottocategorie non seguono la categoria
                            // madre: "Kit Gara" non sconta da solo "Kit Gara
                            // Away", che va aggiunto se serve.
                            ->helperText('Vale per i prodotti della categoria scelta. Le sottocategorie vanno aggiunte a parte.')
                            ->columnSpanFull(),
                    ])->columns(1),
                Forms\Components\Section::make('Validità')
                    ->schema([
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valido Da')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('valid_until')
                            ->label('Valido Fino A')
                            ->nullable(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attivo')
                            ->default(true),
                    ])->columns(3),
                Forms\Components\Textarea::make('description')
                    ->label('Note Interne')
                    ->nullable()
                    ->columnSpanFull()
                    ->rows(2),
            ]);
    }

    /**
     * Il tipo di sconto scelto nel modulo e' la percentuale.
     *
     * La colonna ha il cast a CouponType, e con il valore predefinito il campo
     * tiene l'enum: confrontarlo con la stringa `'percentage'` dava sempre
     * falso, cosi' un coupon percentuale mostrava il suffisso € invece di %,
     * accettava valori oltre 100 e nascondeva il tetto allo sconto.
     */
    private static function eInPercentuale(Forms\Get $get): bool
    {
        $tipo = $get('type');

        if (! $tipo instanceof CouponType) {
            $tipo = CouponType::tryFrom((string) $tipo);
        }

        return $tipo === CouponType::Percentage;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(FontWeight::Bold),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valore')
                    ->formatStateUsing(fn ($state, $record): string => $record->type === CouponType::Percentage ? "{$state}%" : "€{$state}"),
                Tables\Columns\TextColumn::make('ambito')
                    ->label('Si applica a')
                    ->state(fn (Coupon $record): string => $record->haLimitiDiCatalogo()
                        ? 'Solo alcuni prodotti'
                        : 'Tutto il carrello')
                    ->badge()
                    ->color(fn (Coupon $record): string => $record->haLimitiDiCatalogo() ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Utilizzi')
                    ->suffix(fn ($record): string => '/ '.($record->max_uses ?? '∞')),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Scadenza')
                    ->date('d/m/Y')
                    ->placeholder('Nessuna'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
                ...static::timestampColumns(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Attivo'),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo Sconto')
                    ->options(CouponType::class),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions(static::softDeleteBulkActions());
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
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            // La colonna "Si applica a" chiede a ogni riga se ha limiti di
            // catalogo: senza questo sarebbero due query per coupon.
            ->with(['products:id', 'categories:id']);
    }
}

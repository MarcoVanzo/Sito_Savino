<?php

namespace App\Filament\Resources;

use App\Enums\CouponType;
use App\Filament\Resources\CouponResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
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

    protected static ?string $navigationLabel = 'Coupon & Sconti';

    protected static ?string $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'shop/coupon';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                            ->default(CouponType::Percentage),
                        Forms\Components\TextInput::make('value')
                            ->label('Valore')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('max_discount')
                            ->label('Sconto Massimo (€)')
                            ->numeric()
                            ->nullable()
                            ->prefix('€')
                            ->helperText('Solo per coupon percentuali'),
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valore')
                    ->formatStateUsing(fn ($state, $record): string => $record->type === CouponType::Percentage ? "{$state}%" : "€{$state}"),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Utilizzi')
                    ->suffix(fn ($record): string => '/ ' . ($record->max_uses ?? '∞')),
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
            ]);
    }
}

<?php

namespace App\Filament\Resources;

use App\Enums\AuctionStatus;
use App\Filament\Resources\AuctionResource\Pages;
use App\Filament\Resources\AuctionResource\RelationManagers;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Auction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AuctionResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?string $model = Auction::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationLabel = 'Aste';

    protected static ?string $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'shop/aste';

    protected static ?string $modelLabel = 'Asta';

    protected static ?string $pluralModelLabel = 'Aste';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Asta')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Dettagli')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Prodotto')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\TextInput::make('size')
                                    ->label('Taglia (opzionale)')
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('title')
                                    ->label('Titolo')
                                    ->required(),
                                Forms\Components\Textarea::make('description')
                                    ->label('Descrizione')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('status')
                                    ->label('Stato')
                                    ->options(function (?Auction $record): array {
                                        // During creation, only Draft is allowed
                                        if (! $record) {
                                            return [AuctionStatus::Draft->value => AuctionStatus::Draft->getLabel()];
                                        }

                                        // Define allowed transitions per status
                                        $allowedTransitions = [
                                            AuctionStatus::Draft->value => [
                                                AuctionStatus::Draft,
                                                AuctionStatus::Scheduled,
                                                AuctionStatus::Active,
                                                AuctionStatus::Cancelled,
                                            ],
                                            AuctionStatus::Scheduled->value => [
                                                AuctionStatus::Scheduled,
                                                AuctionStatus::Active,
                                                AuctionStatus::Cancelled,
                                            ],
                                            AuctionStatus::Active->value => [
                                                AuctionStatus::Active,
                                                AuctionStatus::Ended,
                                                AuctionStatus::Cancelled,
                                            ],
                                            AuctionStatus::Ended->value => [
                                                AuctionStatus::Ended,
                                            ],
                                            AuctionStatus::Cancelled->value => [
                                                AuctionStatus::Cancelled,
                                                AuctionStatus::Draft,
                                            ],
                                        ];

                                        $currentStatus = $record->status->value ?? AuctionStatus::Draft->value;
                                        $allowed = $allowedTransitions[$currentStatus] ?? AuctionStatus::cases();

                                        return collect($allowed)
                                            ->mapWithKeys(fn (AuctionStatus $s) => [$s->value => $s->getLabel()])
                                            ->all();
                                    })
                                    ->required()
                                    ->default(AuctionStatus::Draft),
                                Forms\Components\Toggle::make('is_charity')
                                    ->label('Asta Benefica'),
                                Forms\Components\Textarea::make('charity_description')
                                    ->label('Descrizione Beneficenza')
                                    ->visible(fn (Forms\Get $get): bool => (bool) $get('is_charity'))
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Prezzi e Offerte')
                            ->schema([
                                Forms\Components\TextInput::make('starting_price')
                                    ->label('Prezzo di Partenza')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required(),
                                Forms\Components\TextInput::make('reserve_price')
                                    ->label('Prezzo di Riserva')
                                    ->numeric()
                                    ->prefix('€')
                                    ->rule('gte:starting_price'),
                                Forms\Components\TextInput::make('bid_increment')
                                    ->label('Incremento Offerta')
                                    ->numeric()
                                    ->default(5)
                                    ->prefix('€'),
                                Forms\Components\TextInput::make('max_bid_jump')
                                    ->label('Salto Massimo Offerta')
                                    ->numeric()
                                    ->default(300)
                                    ->prefix('€'),
                                Forms\Components\TextInput::make('current_bid')
                                    ->label('Offerta Attuale')
                                    ->numeric()
                                    ->disabled()
                                    ->prefix('€')
                                    ->placeholder('Nessuna offerta'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Programmazione')
                            ->schema([
                                Forms\Components\DateTimePicker::make('start_date')
                                    ->label('Data Inizio')
                                    ->required(),
                                Forms\Components\DateTimePicker::make('end_date')
                                    ->label('Data Fine')
                                    ->required()
                                    ->rule('after:start_date'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Vincitore')
                            ->schema([
                                Forms\Components\Select::make('winner_user_id')
                                    ->label('Vincitore')
                                    ->relationship('winner', 'name')
                                    ->disabled(),
                                Forms\Components\TextInput::make('current_winner_attempt')
                                    ->label('Tentativo Vincitore')
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('winner_checkout_deadline')
                                    ->label('Scadenza Checkout Vincitore')
                                    ->disabled(),
                            ])->columns(2)
                            ->visibleOn('edit'),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titolo')
                    ->limit(30)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starting_price')
                    ->label('Prezzo Partenza')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('current_bid')
                    ->label('Offerta Attuale')
                    ->money('EUR')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('bids_count')
                    ->label('Offerte')
                    ->counts('bids')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Scadenza')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_charity')
                    ->label('Benefica')
                    ->boolean(),
                ...static::timestampColumns(),
            ])
            ->defaultSort('end_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options(AuctionStatus::class),
                Tables\Filters\TernaryFilter::make('is_charity')
                    ->label('Asta Benefica'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions(static::viewAndEditActions())
            ->bulkActions(static::softDeleteBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BidsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuctions::route('/'),
            'create' => Pages\CreateAuction::route('/create'),
            'edit' => Pages\EditAuction::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['product', 'winner'])
            ->withCount('bids');
    }
}

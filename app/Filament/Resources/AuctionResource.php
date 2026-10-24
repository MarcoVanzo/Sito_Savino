<?php

namespace App\Filament\Resources;

use App\Enums\AuctionStatus;
use App\Filament\Resources\AuctionResource\Pages\CreateAuction;
use App\Filament\Resources\AuctionResource\Pages\EditAuction;
use App\Filament\Resources\AuctionResource\Pages\ListAuctions;
use App\Filament\Resources\AuctionResource\RelationManagers\BidsRelationManager;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\Auction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class AuctionResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?string $model = Auction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationLabel = 'Aste';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'shop/aste';

    protected static ?string $modelLabel = 'Asta';

    protected static ?string $pluralModelLabel = 'Aste';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Asta')
                    ->tabs([
                        Tab::make('Dettagli')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Prodotto')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('size')
                                    ->label('Taglia (opzionale)')
                                    ->maxLength(50),
                                TextInput::make('title')
                                    ->label('Titolo')
                                    ->required(),
                                Textarea::make('description')
                                    ->label('Descrizione')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Select::make('status')
                                    ->label('Stato')
                                    // Le transizioni ammesse stanno sul model
                                    // (Auction::TRANSIZIONI_AMMESSE): qui si
                                    // disegnano le opzioni, li' si applicano.
                                    ->options(fn (?Auction $record): array => $record
                                        ? Auction::statiRaggiungibiliDa($record->status)
                                        : [AuctionStatus::Draft->value => AuctionStatus::Draft->getLabel()])
                                    ->required()
                                    ->default(AuctionStatus::Draft)
                                    ->helperText(fn (?Auction $record): string => $record
                                        ? 'Sul sito compaiono solo le aste Attive, Programmate e Concluse: una bozza resta invisibile.'
                                        : 'L’asta nasce in bozza e non è ancora visibile sul sito. Dopo il salvataggio riaprila e portala su “Attiva” o “Programmata”.'),
                                Toggle::make('is_charity')
                                    ->label('Asta Benefica'),
                                Textarea::make('charity_description')
                                    ->label('Descrizione Beneficenza')
                                    ->visible(fn (Get $get): bool => (bool) $get('is_charity'))
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Tab::make('Prezzi e Offerte')
                            ->schema([
                                TextInput::make('starting_price')
                                    ->label('Prezzo di Partenza')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required(),
                                TextInput::make('reserve_price')
                                    ->label('Prezzo di Riserva')
                                    ->numeric()
                                    ->prefix('€')
                                    // Vedi ProductResource: la regola scritta a mano
                                    // non risolve il percorso `data.` e fallisce sempre.
                                    ->gte('starting_price'),
                                TextInput::make('bid_increment')
                                    ->label('Incremento Offerta')
                                    ->numeric()
                                    ->default(5)
                                    ->prefix('€'),
                                TextInput::make('max_bid_jump')
                                    ->label('Salto Massimo Offerta')
                                    ->numeric()
                                    ->default(300)
                                    ->prefix('€'),
                                TextInput::make('current_bid')
                                    ->label('Offerta Attuale')
                                    ->numeric()
                                    ->disabled()
                                    ->prefix('€')
                                    ->placeholder('Nessuna offerta'),
                            ])->columns(2),

                        Tab::make('Programmazione')
                            ->schema([
                                DateTimePicker::make('start_date')
                                    ->label('Data Inizio')
                                    ->required(),
                                DateTimePicker::make('end_date')
                                    ->label('Data Fine')
                                    ->required()
                                    ->after('start_date'),
                            ])->columns(2),

                        Tab::make('Vincitore')
                            ->schema([
                                Select::make('winner_user_id')
                                    ->label('Vincitore')
                                    ->relationship('winner', 'name')
                                    ->disabled(),
                                TextInput::make('current_winner_attempt')
                                    ->label('Tentativo Vincitore')
                                    ->disabled(),
                                DateTimePicker::make('winner_checkout_deadline')
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
                TextColumn::make('title')
                    ->label('Titolo')
                    ->limit(30)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->sortable(),
                TextColumn::make('starting_price')
                    ->label('Prezzo Partenza')
                    ->money('EUR'),
                TextColumn::make('current_bid')
                    ->label('Offerta Attuale')
                    ->money('EUR')
                    ->placeholder('-'),
                TextColumn::make('bids_count')
                    ->label('Offerte')
                    ->counts('bids')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Scadenza')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                IconColumn::make('is_charity')
                    ->label('Benefica')
                    ->boolean(),
                ...static::timestampColumns(),
            ])
            ->defaultSort('end_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options(AuctionStatus::class),
                TernaryFilter::make('is_charity')
                    ->label('Asta Benefica'),
                TrashedFilter::make(),
            ])
            ->recordActions(static::viewAndEditActions())
            ->toolbarActions(static::softDeleteBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            BidsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuctions::route('/'),
            'create' => CreateAuction::route('/create'),
            'edit' => EditAuction::route('/{record}/edit'),
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

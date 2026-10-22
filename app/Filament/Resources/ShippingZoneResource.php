<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone;
use App\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone;
use App\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\ShippingZone;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class ShippingZoneResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?string $model = ShippingZone::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Zona Spedizione';

    protected static ?string $pluralModelLabel = 'Zone Spedizione';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Zone Spedizione';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'shop/zone-spedizione';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli Zona')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome Zona')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TagsInput::make('countries')
                            ->label('Codici Paese (ISO 3166-1)')
                            ->helperText('Es: IT, DE, FR. Usa * per catch-all'),
                        TextInput::make('flat_rate')
                            ->label('Tariffa Base (€)')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        TextInput::make('free_threshold')
                            ->label('Soglia Spedizione Gratuita (€)')
                            ->numeric()
                            ->prefix('€')
                            ->nullable()
                            // La soglia globale delle Impostazioni Shop, se
                            // compilata, vince su questa (ShippingZone::sogliaGratuita):
                            // va detto qui, o la redazione cambia un numero che non conta.
                            ->helperText(fn (): string => ShippingZone::sogliaGlobale() !== null
                                ? 'Ora non conta: vale la soglia globale di '.number_format(ShippingZone::sogliaGlobale(), 2, ',', '.').' € delle Impostazioni Shop. Svuotala lì per usare questa.'
                                : 'Lasciare vuoto per disabilitare. Se si compila la soglia globale nelle Impostazioni Shop, vale quella per tutte le zone.'),
                        TextInput::make('estimated_days_min')
                            ->label('Giorni Min')
                            ->numeric(),
                        TextInput::make('estimated_days_max')
                            ->label('Giorni Max')
                            ->numeric(),
                        Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->label('Ordine')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Section::make('Fasce di peso')
                    ->description('Tariffe diverse a seconda del peso del collo. Lascia l\'elenco vuoto per applicare sempre la tariffa base.')
                    ->schema([
                        Repeater::make('weight_rates')
                            ->label('')
                            // Facoltativo: con una riga aperta d'ufficio, una
                            // zona nuova non si salverebbe finche' non la si
                            // compila o la si cancella.
                            ->defaultItems(0)
                            ->addActionLabel('Aggiungi una fascia')
                            ->schema([
                                TextInput::make('max_weight')
                                    ->label('Fino a (kg)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix('kg')
                                    ->helperText('Vuoto = da qui in su. Tienine al massimo una senza limite.'),
                                TextInput::make('rate')
                                    ->label('Tariffa (€)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('€')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): string => match (true) {
                                ! isset($state['rate']) => 'Nuova fascia',
                                blank($state['max_weight'] ?? null) => 'Oltre l\'ultima fascia — € '.$state['rate'],
                                default => 'Fino a '.$state['max_weight'].' kg — € '.$state['rate'],
                            })
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('countries')
                    ->label('Paesi')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : ($state ?? '')),
                TextColumn::make('flat_rate')
                    ->label('Tariffa')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('fasce')
                    ->label('Fasce di peso')
                    ->state(fn (ShippingZone $record): string => $record->fasceOrdinate() === []
                        ? 'Solo tariffa base'
                        : count($record->fasceOrdinate()).' fasce')
                    ->badge()
                    ->color(fn (ShippingZone $record): string => $record->fasceOrdinate() === [] ? 'gray' : 'success'),
                TextColumn::make('free_threshold')
                    ->label('Soglia Gratis')
                    ->money('EUR')
                    ->placeholder('-'),
                IconColumn::make('is_active')
                    ->label('Attiva')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
                ...static::timestampColumns(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions(static::standardBulkActions());
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
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}

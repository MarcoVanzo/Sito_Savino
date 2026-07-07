<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingZoneResource\Pages;
use App\Filament\Traits\HasStandardTableActions;
use App\Models\ShippingZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingZoneResource extends Resource
{
    use HasStandardTableActions;
    use Translatable;

    protected static ?string $model = ShippingZone::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Zona Spedizione';

    protected static ?string $pluralModelLabel = 'Zone Spedizione';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Zone Spedizione';

    protected static ?string $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'shop/zone-spedizione';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Zona')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Zona')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TagsInput::make('countries')
                            ->label('Codici Paese (ISO 3166-1)')
                            ->helperText('Es: IT, DE, FR. Usa * per catch-all'),
                        Forms\Components\TextInput::make('flat_rate')
                            ->label('Tariffa Base (€)')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        Forms\Components\TextInput::make('free_threshold')
                            ->label('Soglia Spedizione Gratuita (€)')
                            ->numeric()
                            ->prefix('€')
                            ->nullable()
                            ->helperText('Lasciare vuoto per disabilitare'),
                        Forms\Components\TextInput::make('estimated_days_min')
                            ->label('Giorni Min')
                            ->numeric(),
                        Forms\Components\TextInput::make('estimated_days_max')
                            ->label('Giorni Max')
                            ->numeric(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Ordine')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('countries')
                    ->label('Paesi')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : ($state ?? '')),
                Tables\Columns\TextColumn::make('flat_rate')
                    ->label('Tariffa')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('free_threshold')
                    ->label('Soglia Gratis')
                    ->money('EUR')
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Attiva')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
                ...static::timestampColumns(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions(static::standardBulkActions());
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
            'index' => Pages\ListShippingZones::route('/'),
            'create' => Pages\CreateShippingZone::route('/create'),
            'edit' => Pages\EditShippingZone::route('/{record}/edit'),
        ];
    }
}

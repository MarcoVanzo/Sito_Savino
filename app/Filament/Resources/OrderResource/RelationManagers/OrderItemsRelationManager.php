<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Articoli Ordine';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Prodotto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('product_variant_id')
                    ->label('Variante')
                    ->relationship('variant', 'sku', fn (Builder $query, Get $get) => $query->where('product_id', $get('product_id'))
                    )
                    ->searchable()
                    ->preload(),
                TextInput::make('quantity')
                    ->label('Quantità')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                TextInput::make('price_at_time_of_purchase')
                    ->label('Prezzo Unitario (€)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€'),
            ]);
    }

    /**
     * Le righe d'ordine sono di sola lettura nel pannello, per ogni stato.
     *
     * Aggiungere, cambiare o togliere una riga da qui non passava dal
     * magazzino: la riga nuova vendeva merce senza scaricarla, quella tolta
     * lasciava scaricata merce mai venduta, e su un ordine pagato il totale si
     * staccava dall'importo incassato dal gateway. Far passare le modifiche
     * da `Order::registraArticolo` e dagli StockMovement su un ordine in
     * attesa sarebbe possibile, ma quel totale e' anche l'importo della
     * sessione di pagamento gia' aperta dal cliente (Stripe/PayPal) o del
     * bonifico che gli e' stato chiesto: cambiarlo a meta' strada crea un
     * incasso discorde (HandlesPaymentWebhooks::importoDiscorde). Un ordine
     * sbagliato si annulla (merce e coupon tornano da soli) e se ne fa un
     * altro. OrderItemPolicy nega comunque la modifica fuori da Pending.
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'variant']))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Prodotto')
                    ->searchable(),
                TextColumn::make('variant.sku')
                    ->label('Variante SKU')
                    ->placeholder('—'),
                // Chi prepara il pacco deve sapere che la maglia va firmata.
                TextColumn::make('personalizzazione')
                    ->label('Personalizzazione')
                    ->state(fn ($record) => $record->personalizzazioneIn('it'))
                    ->description(fn ($record) => (float) $record->supplemento_personalizzazione > 0
                        ? '+ € '.number_format((float) $record->supplemento_personalizzazione, 2, ',', '.')
                        : null)
                    ->badge()
                    ->color('danger')
                    ->placeholder('—'),
                // Lo stato dichiarato nella scheda al momento dell'acquisto
                // (maglie indossate, autografi): e' cio' che e' stato venduto,
                // anche se la scheda nel frattempo e' cambiata.
                TextColumn::make('stato_articolo')
                    ->label('Stato dell\'articolo')
                    ->state(fn ($record) => $record->statoArticoloIn('it'))
                    ->wrap()
                    ->limit(160)
                    ->tooltip(fn ($record) => $record->statoArticoloIn('it'))
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->label('Quantità')
                    ->numeric(),
                TextColumn::make('price_at_time_of_purchase')
                    ->label('Prezzo Unitario')
                    ->money('EUR'),
            ])
            ->filters([
                //
            ])
            // Nessuna azione: vedi isReadOnly().
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

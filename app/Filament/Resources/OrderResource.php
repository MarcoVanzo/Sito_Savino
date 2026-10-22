<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Exceptions\UnsupportedPaymentGatewayException;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Filament\Traits\HasStandardTableActions;
use App\Mail\OrderShipped;
use App\Models\Order;
use App\Services\AdminNotificationService;
use App\Services\Payments\PayPalPaymentService;
use App\Services\Payments\StripePaymentService;
use App\Services\ReceiptService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Order::class;

    /** Etichetta dello stato ordine: form, tabella e scheda devono coincidere. */
    private const LABEL_ORDER_STATUS = 'Stato Ordine';

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $isGloballySearchable = false;

    protected static ?string $modelLabel = 'Ordine';

    protected static ?string $pluralModelLabel = 'Ordini';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Ordini';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'shop/ordini';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Ordine')
                    ->tabs([
                        Tab::make('Dettagli')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                TextInput::make('order_number')
                                    ->label('Numero Ordine')
                                    ->disabled()
                                    ->dehydrated(false),
                                // Solo in lettura: `status` e' fuori da $fillable e
                                // il valore scelto qui finiva nel nulla senza dirlo.
                                // Lo stato si cambia con le azioni dedicate (Conferma
                                // pagamento, Segna spedito, Annulla, Rimborso), che
                                // ammettono solo le transizioni sensate e portano con
                                // se' magazzino, coupon ed email.
                                Select::make('status')
                                    ->label(self::LABEL_ORDER_STATUS)
                                    ->options(OrderStatus::class)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Si cambia con le azioni dell\'ordine (Conferma pagamento, Segna spedito, Annulla, Rimborso).'),
                                Select::make('user_id')
                                    ->label('Cliente (Utente)')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->nullable(),
                                Select::make('payment_gateway')
                                    ->label('Gateway Pagamento')
                                    ->options(PaymentGateway::class)
                                    ->nullable(),
                                TextInput::make('payment_id')
                                    ->label('ID Pagamento')
                                    ->disabled()
                                    ->dehydrated(false),
                                DateTimePicker::make('paid_at')
                                    ->label('Pagato il')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('codice_fiscale')
                                    ->label('Codice Fiscale'),
                            ])->columns(2),

                        Tab::make('Cliente Guest')
                            ->icon('heroicon-o-user')
                            ->schema([
                                TextInput::make('guest_name')
                                    ->label('Nome Guest')
                                    ->disabled(),
                                TextInput::make('guest_email')
                                    ->label('Email Guest')
                                    ->disabled(),
                                TextInput::make('guest_phone')
                                    ->label('Telefono Guest')
                                    ->disabled(),
                                TextInput::make('phone')
                                    ->label('Telefono (Utente registrato)')
                                    ->disabled(),
                            ])->columns(3),

                        Tab::make('Spedizione')
                            ->icon('heroicon-o-truck')
                            ->schema([
                                // Indirizzo strutturato (nuovi ordini)
                                Fieldset::make('Indirizzo di Spedizione')
                                    ->schema([
                                        TextInput::make('shipping_address.first_name')
                                            ->label('Nome'),
                                        TextInput::make('shipping_address.last_name')
                                            ->label('Cognome'),
                                        TextInput::make('shipping_address.street')
                                            ->label('Via')
                                            ->columnSpanFull(),
                                        TextInput::make('shipping_address.city')
                                            ->label('Città'),
                                        TextInput::make('shipping_address.zip_code')
                                            ->label('CAP'),
                                        TextInput::make('shipping_address.province')
                                            ->label('Provincia'),
                                        // Fallback per vecchi ordini con indirizzo in testo libero
                                        Textarea::make('shipping_address.raw_address')
                                            ->label('Indirizzo (vecchio formato)')
                                            ->rows(2)
                                            ->visible(fn ($record) => isset($record?->shipping_address['raw_address']))
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                // Indirizzo fatturazione strutturato
                                Fieldset::make('Indirizzo di Fatturazione')
                                    ->schema([
                                        TextInput::make('billing_address.first_name')
                                            ->label('Nome'),
                                        TextInput::make('billing_address.last_name')
                                            ->label('Cognome'),
                                        TextInput::make('billing_address.street')
                                            ->label('Via')
                                            ->columnSpanFull(),
                                        TextInput::make('billing_address.city')
                                            ->label('Città'),
                                        TextInput::make('billing_address.zip_code')
                                            ->label('CAP'),
                                        TextInput::make('billing_address.province')
                                            ->label('Provincia'),
                                        Textarea::make('billing_address.raw_address')
                                            ->label('Indirizzo (vecchio formato)')
                                            ->rows(2)
                                            ->visible(fn ($record) => isset($record?->billing_address['raw_address']))
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                TextInput::make('country')
                                    ->label('Paese (Spedizione)')
                                    ->maxLength(2),
                                TextInput::make('billing_country')
                                    ->label('Paese (Fatturazione)')
                                    ->maxLength(2),
                                TextInput::make('tracking_number')
                                    ->label('Numero Tracking'),
                                TextInput::make('tracking_url')
                                    ->label('URL Tracking')
                                    ->url(),
                                DateTimePicker::make('shipped_at')
                                    ->label('Spedito il'),
                            ])->columns(2),

                        Tab::make('Importi')
                            ->icon('heroicon-o-currency-euro')
                            ->schema([
                                TextInput::make('total_price')
                                    ->label('Totale Ordine')
                                    ->disabled()
                                    ->prefix('€'),
                                TextInput::make('shipping_cost')
                                    ->label('Costo Spedizione')
                                    ->disabled()
                                    ->prefix('€'),
                                TextInput::make('coupon_discount')
                                    ->label('Sconto Coupon')
                                    ->disabled()
                                    ->prefix('€'),
                                Select::make('coupon_id')
                                    ->label('Coupon')
                                    ->relationship('coupon', 'code')
                                    ->disabled(),
                            ])->columns(2),

                        Tab::make('Note')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Textarea::make('notes')
                                    ->label('Note')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(self::colonne())
            ->defaultSort('created_at', 'desc')
            ->filters(self::filtri())
            ->recordActions(self::azioni())
            // Niente cancellazione, né in blocco né singola: gli ordini sono
            // documenti contabili da conservare (OrderPolicy). Resta il
            // ripristino di quelli cancellati prima di questa regola.
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int, Column>
     */
    private static function colonne(): array
    {
        return [
            TextColumn::make('order_number')
                ->label('Numero Ordine')
                ->searchable()
                ->sortable()
                ->weight(FontWeight::Bold),
            TextColumn::make('customer_name')
                ->label('Cliente')
                ->getStateUsing(fn ($record) => $record->user->name ?? $record->guest_name ?? '-')
                ->url(fn ($record) => $record->user_id ? UserResource::getUrl('edit', ['record' => $record->user_id]) : null)
                ->color(fn ($record) => $record->user_id ? 'primary' : null)
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->where(function (Builder $query) use ($search) {
                        $query->whereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhere('guest_name', 'like', "%{$search}%")
                            ->orWhere('guest_email', 'like', "%{$search}%");
                    });
                }),
            TextColumn::make('contact_phone')
                ->label('Telefono')
                ->getStateUsing(fn ($record) => $record->phone ?? $record->guest_phone ?? '-')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')
                ->label(self::LABEL_ORDER_STATUS)
                ->badge()
                ->sortable(),
            TextColumn::make('payment_gateway')
                ->label('Pagamento')
                ->badge()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('total_price')
                ->label('Totale')
                ->money('EUR')
                ->sortable(),
            TextColumn::make('created_at')
                ->label('Data Ordine')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ];
    }

    /**
     * @return array<int, BaseFilter>
     */
    private static function filtri(): array
    {
        return [
            SelectFilter::make('status')
                ->label(self::LABEL_ORDER_STATUS)
                ->options(OrderStatus::class),
            SelectFilter::make('payment_gateway')
                ->label('Gateway Pagamento')
                ->options(PaymentGateway::class),
            Filter::make('created_at')
                ->label('Periodo')
                ->schema([
                    DatePicker::make('from')
                        ->label('Da'),
                    DatePicker::make('until')
                        ->label('A'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        );
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];
                    if ($data['from'] ?? null) {
                        $indicators[] = Indicator::make('Da: '.Carbon::parse($data['from'])->format('d/m/Y'))
                            ->removeField('from');
                    }
                    if ($data['until'] ?? null) {
                        $indicators[] = Indicator::make('A: '.Carbon::parse($data['until'])->format('d/m/Y'))
                            ->removeField('until');
                    }

                    return $indicators;
                }),
            TrashedFilter::make(),
        ];
    }

    /**
     * Le azioni sull'ordine: conferma del bonifico, rimborso, cambio di stato
     * e le due standard di visualizzazione e modifica.
     *
     * @return array<int, Action|ActionGroup>
     */
    private static function azioni(): array
    {
        return [
            ...self::azioniDiStato(),
            ...self::azioniSuPagamentiEDocumenti(),

            // Standard view & edit
            ...static::viewAndEditActions(),
        ];
    }

    /**
     * Avanzamento dell'ordine: conferma del bonifico, spedizione, consegna,
     * annullamento.
     *
     * @return array<int, Action>
     */
    private static function azioniDiStato(): array
    {
        return [
            // 1. Conferma Pagamento (solo bonifico bancario)
            Action::make('confirm_payment')
                ->label('Conferma Pagamento')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending
                    && $record->payment_gateway === PaymentGateway::BankTransfer
                )
                ->requiresConfirmation()
                ->modalHeading('Conferma pagamento bonifico')
                ->modalDescription('Confermi di aver ricevuto il pagamento tramite bonifico?')
                ->modalSubmitActionLabel('Conferma ricevuto')
                ->action(function (Order $record): void {
                    $record->status = OrderStatus::Processing;
                    $record->paid_at = now();
                    $record->save();

                    app(AdminNotificationService::class)->notifyPaymentReceived($record);

                    Cache::forget('filament:dashboard:stats');

                    Notification::make()
                        ->title('Pagamento confermato')
                        ->body("Ordine #{$record->order_number} aggiornato a In Lavorazione.")
                        ->success()
                        ->send();
                }),

            // 2. Segna Spedito
            Action::make('mark_shipped')
                ->label('Segna Spedito')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->visible(fn (Order $record): bool => in_array($record->status, [
                    OrderStatus::Processing,
                    OrderStatus::Paid,
                ]))
                ->schema([
                    TextInput::make('tracking_number')
                        ->label('Numero Tracking')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('tracking_url')
                        ->label('URL Tracking')
                        ->url()
                        ->maxLength(500),
                ])
                ->modalHeading('Inserisci dati spedizione')
                ->modalSubmitActionLabel('Conferma spedizione')
                ->action(function (Order $record, array $data): void {
                    $record->tracking_number = $data['tracking_number'];
                    $record->tracking_url = $data['tracking_url'] ?? null;
                    $record->shipped_at = now();
                    $record->status = OrderStatus::Shipped;
                    $record->save();

                    // Invia email di spedizione al cliente
                    $recipientEmail = $record->user->email ?? $record->guest_email;
                    if ($recipientEmail) {
                        Mail::to($recipientEmail)->queue(new OrderShipped($record));
                    }

                    Cache::forget('filament:dashboard:stats');

                    Notification::make()
                        ->title('Ordine spedito')
                        ->body("Ordine #{$record->order_number} segnato come spedito. Email inviata.")
                        ->success()
                        ->send();
                }),

            // 3. Segna Consegnato
            Action::make('mark_delivered')
                ->label('Segna Consegnato')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Shipped)
                ->requiresConfirmation()
                ->modalHeading('Conferma consegna')
                ->modalDescription('Confermi che l\'ordine è stato consegnato?')
                ->action(function (Order $record): void {
                    $record->status = OrderStatus::Delivered;
                    $record->save();

                    Cache::forget('filament:dashboard:stats');

                    Notification::make()
                        ->title('Ordine consegnato')
                        ->body("Ordine #{$record->order_number} segnato come consegnato.")
                        ->success()
                        ->send();
                }),

            // 4. Annulla Ordine
            Action::make('cancel_order')
                ->label('Annulla')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Order $record): bool => in_array($record->status, [
                    OrderStatus::Pending,
                    OrderStatus::Processing,
                    OrderStatus::Paid,
                ]))
                ->requiresConfirmation()
                ->modalHeading('Annulla ordine')
                ->modalDescription('Sei sicuro? Lo stock verrà ripristinato.')
                ->modalSubmitActionLabel('Annulla ordine')
                ->action(function (Order $record): void {
                    DB::transaction(function () use ($record) {
                        // L'OrderObserver gestisce il ripristino stock
                        // tramite restoreStock() quando lo status diventa Cancelled
                        $record->status = OrderStatus::Cancelled;
                        $record->save();
                    });

                    Cache::forget('filament:dashboard:stats');

                    $body = "Ordine #{$record->order_number} annullato. Stock ripristinato.";
                    if ($record->paid_at) {
                        $body .= ' ⚠️ Il pagamento era stato ricevuto: potrebbe essere necessario un rimborso manuale.';
                    }

                    Notification::make()
                        ->title('Ordine annullato')
                        ->body($body)
                        ->warning()
                        ->send();
                }),
        ];
    }

    /**
     * Rimborso e ricevuta: le due azioni che toccano il denaro incassato.
     *
     * @return array<int, Action>
     */
    private static function azioniSuPagamentiEDocumenti(): array
    {
        return [
            // 5. Rimborso (Stripe/PayPal)
            Action::make('refund_order')
                ->label('Rimborso')
                ->icon('heroicon-o-receipt-refund')
                ->color('warning')
                ->visible(fn (Order $record): bool => $record->payment_id !== null &&
                    in_array($record->payment_gateway, [PaymentGateway::Stripe, PaymentGateway::PayPal]) &&
                    $record->status !== OrderStatus::Refunded
                )
                ->schema([
                    Radio::make('refund_type')
                        ->label('Tipo di Rimborso')
                        ->options([
                            'full' => 'Rimborso Totale',
                            'partial' => 'Rimborso Parziale',
                        ])
                        ->default('full')
                        ->required()
                        ->live(), // filament 3 uses live() instead of reactive()
                    TextInput::make('amount')
                        ->label('Importo (EUR)')
                        ->numeric()
                        ->prefix('€')
                        ->required()
                        ->visible(fn (Get $get) => $get('refund_type') === 'partial')
                        ->maxValue(fn (Order $record) => (float) $record->total_price)
                        ->minValue(0.01)
                        ->step(0.01),
                ])
                ->modalHeading('Emetti Rimborso')
                // Il testo diceva che lo stock non cambia: il rimborso totale
                // porta l'ordine a Rimborsato e l'OrderObserver rimette la merce
                // in giacenza; il parziale (anche quando torna dal gateway via
                // webhook) registra solo l'importo.
                ->modalDescription('Il rimborso verrà emesso sul metodo di pagamento originale del cliente. Rimborso totale: l\'ordine passa a Rimborsato e la merce torna in giacenza. Rimborso parziale: l\'ordine resta nel suo stato, la giacenza non cambia e l\'importo rimborsato viene registrato.')
                ->modalSubmitActionLabel('Emetti Rimborso')
                ->action(function (Order $record, array $data): void {
                    try {
                        $service = match ($record->payment_gateway) {
                            PaymentGateway::Stripe => new StripePaymentService,
                            PaymentGateway::PayPal => new PayPalPaymentService,
                            default => throw new UnsupportedPaymentGatewayException('Gateway non supportato per il rimborso automatico.'),
                        };

                        $amount = $data['refund_type'] === 'partial' ? (float) $data['amount'] : null;

                        $service->refund($record, $amount);

                        if ($data['refund_type'] === 'full') {
                            // Il webhook che segue trova l'ordine gia' Rimborsato e
                            // si ferma: l'importo si registra qui.
                            $record->refunded_amount = $record->total_price;
                            $record->status = OrderStatus::Refunded;
                            $record->save();
                        }

                        Cache::forget('filament:dashboard:stats');

                        $amountFormatted = $amount ? 'di €'.number_format($amount, 2, ',', '.') : 'totale';

                        Notification::make()
                            ->title('Rimborso emesso con successo')
                            ->body("Il rimborso {$amountFormatted} è stato elaborato dal gateway.")
                            ->success()
                            ->send();

                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Errore durante il rimborso')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // 6. Scarica Ricevuta
            Action::make('download_receipt')
                ->label('Ricevuta')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (Order $record) {
                    return response()->streamDownload(
                        function () use ($record) {
                            echo app(ReceiptService::class)->generate($record);
                        },
                        'ricevuta-'.$record->order_number.'.pdf',
                        ['Content-Type' => 'application/pdf']
                    );
                })
                ->visible(fn (Order $record): bool => $record->status !== OrderStatus::Cancelled),
        ];
    }

    public static function getRelations(): array
    {
        return [
            OrderItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['user', 'coupon']);
    }
}

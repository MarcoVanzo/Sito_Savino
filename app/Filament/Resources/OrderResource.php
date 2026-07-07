<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Filament\Traits\HasStandardTableActions;
use App\Mail\OrderShipped;
use App\Models\Order;
use App\Services\AdminNotificationService;
use App\Services\Payments\PayPalPaymentService;
use App\Services\Payments\StripePaymentService;
use App\Services\ReceiptService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrderResource extends Resource
{
    use HasStandardTableActions;

    protected static ?string $model = Order::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $isGloballySearchable = false;

    protected static ?string $modelLabel = 'Ordine';

    protected static ?string $pluralModelLabel = 'Ordini';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Ordini';

    protected static ?string $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'shop/ordini';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Ordine')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Dettagli')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\TextInput::make('order_number')
                                    ->label('Numero Ordine')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\Select::make('status')
                                    ->label('Stato Ordine')
                                    ->options(OrderStatus::class)
                                    ->required()
                                    ->default(OrderStatus::Pending),
                                Forms\Components\Select::make('user_id')
                                    ->label('Cliente (Utente)')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->nullable(),
                                Forms\Components\Select::make('payment_gateway')
                                    ->label('Gateway Pagamento')
                                    ->options(PaymentGateway::class)
                                    ->nullable(),
                                Forms\Components\TextInput::make('payment_id')
                                    ->label('ID Pagamento')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\DateTimePicker::make('paid_at')
                                    ->label('Pagato il')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\TextInput::make('codice_fiscale')
                                    ->label('Codice Fiscale'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Cliente Guest')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\TextInput::make('guest_name')
                                    ->label('Nome Guest')
                                    ->disabled(),
                                Forms\Components\TextInput::make('guest_email')
                                    ->label('Email Guest')
                                    ->disabled(),
                                Forms\Components\TextInput::make('guest_phone')
                                    ->label('Telefono Guest')
                                    ->disabled(),
                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefono (Utente registrato)')
                                    ->disabled(),
                            ])->columns(3),

                        Forms\Components\Tabs\Tab::make('Spedizione')
                            ->icon('heroicon-o-truck')
                            ->schema([
                                // Indirizzo strutturato (nuovi ordini)
                                Forms\Components\Fieldset::make('Indirizzo di Spedizione')
                                    ->schema([
                                        Forms\Components\TextInput::make('shipping_address.first_name')
                                            ->label('Nome'),
                                        Forms\Components\TextInput::make('shipping_address.last_name')
                                            ->label('Cognome'),
                                        Forms\Components\TextInput::make('shipping_address.street')
                                            ->label('Via')
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('shipping_address.city')
                                            ->label('Città'),
                                        Forms\Components\TextInput::make('shipping_address.zip_code')
                                            ->label('CAP'),
                                        Forms\Components\TextInput::make('shipping_address.province')
                                            ->label('Provincia'),
                                        // Fallback per vecchi ordini con indirizzo in testo libero
                                        Forms\Components\Textarea::make('shipping_address.raw_address')
                                            ->label('Indirizzo (vecchio formato)')
                                            ->rows(2)
                                            ->visible(fn ($record) => isset($record?->shipping_address['raw_address']))
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                // Indirizzo fatturazione strutturato
                                Forms\Components\Fieldset::make('Indirizzo di Fatturazione')
                                    ->schema([
                                        Forms\Components\TextInput::make('billing_address.first_name')
                                            ->label('Nome'),
                                        Forms\Components\TextInput::make('billing_address.last_name')
                                            ->label('Cognome'),
                                        Forms\Components\TextInput::make('billing_address.street')
                                            ->label('Via')
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('billing_address.city')
                                            ->label('Città'),
                                        Forms\Components\TextInput::make('billing_address.zip_code')
                                            ->label('CAP'),
                                        Forms\Components\TextInput::make('billing_address.province')
                                            ->label('Provincia'),
                                        Forms\Components\Textarea::make('billing_address.raw_address')
                                            ->label('Indirizzo (vecchio formato)')
                                            ->rows(2)
                                            ->visible(fn ($record) => isset($record?->billing_address['raw_address']))
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                Forms\Components\TextInput::make('country')
                                    ->label('Paese (Spedizione)')
                                    ->maxLength(2),
                                Forms\Components\TextInput::make('billing_country')
                                    ->label('Paese (Fatturazione)')
                                    ->maxLength(2),
                                Forms\Components\TextInput::make('tracking_number')
                                    ->label('Numero Tracking'),
                                Forms\Components\TextInput::make('tracking_url')
                                    ->label('URL Tracking')
                                    ->url(),
                                Forms\Components\DateTimePicker::make('shipped_at')
                                    ->label('Spedito il'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Importi')
                            ->icon('heroicon-o-currency-euro')
                            ->schema([
                                Forms\Components\TextInput::make('total_price')
                                    ->label('Totale Ordine')
                                    ->disabled()
                                    ->prefix('€'),
                                Forms\Components\TextInput::make('shipping_cost')
                                    ->label('Costo Spedizione')
                                    ->disabled()
                                    ->prefix('€'),
                                Forms\Components\TextInput::make('coupon_discount')
                                    ->label('Sconto Coupon')
                                    ->disabled()
                                    ->prefix('€'),
                                Forms\Components\Select::make('coupon_id')
                                    ->label('Coupon')
                                    ->relationship('coupon', 'code')
                                    ->disabled(),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('Note')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
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
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Numero Ordine')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->getStateUsing(fn ($record) => $record->user?->name ?? $record->guest_name ?? '-')
                    ->url(fn ($record) => $record->user_id ? UserResource::getUrl('edit', ['record' => $record->user_id]) : null)
                    ->color(fn ($record) => $record->user_id ? 'primary' : null)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query->whereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                                ->orWhere('guest_name', 'like', "%{$search}%")
                                ->orWhere('guest_email', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('Telefono')
                    ->getStateUsing(fn ($record) => $record->phone ?? $record->guest_phone ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato Ordine')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_gateway')
                    ->label('Pagamento')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Totale')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data Ordine')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato Ordine')
                    ->options(OrderStatus::class),
                Tables\Filters\SelectFilter::make('payment_gateway')
                    ->label('Gateway Pagamento')
                    ->options(PaymentGateway::class),
                Tables\Filters\Filter::make('created_at')
                    ->label('Periodo')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Da'),
                        Forms\Components\DatePicker::make('until')
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
                            $indicators[] = Tables\Filters\Indicator::make('Da: '.Carbon::parse($data['from'])->format('d/m/Y'))
                                ->removeField('from');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('A: '.Carbon::parse($data['until'])->format('d/m/Y'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // 1. Conferma Pagamento (solo bonifico bancario)
                Tables\Actions\Action::make('confirm_payment')
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
                Tables\Actions\Action::make('mark_shipped')
                    ->label('Segna Spedito')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (Order $record): bool => in_array($record->status, [
                        OrderStatus::Processing,
                        OrderStatus::Paid,
                    ]))
                    ->form([
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Numero Tracking')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('tracking_url')
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
                        $recipientEmail = $record->user?->email ?? $record->guest_email;
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
                Tables\Actions\Action::make('mark_delivered')
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
                Tables\Actions\Action::make('cancel_order')
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

                // 5. Rimborso (Stripe/PayPal)
                Tables\Actions\Action::make('refund_order')
                    ->label('Rimborso')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('warning')
                    ->visible(fn (Order $record): bool => $record->payment_id !== null &&
                        in_array($record->payment_gateway, [PaymentGateway::Stripe, PaymentGateway::PayPal]) &&
                        $record->status !== OrderStatus::Refunded
                    )
                    ->form([
                        Forms\Components\Radio::make('refund_type')
                            ->label('Tipo di Rimborso')
                            ->options([
                                'full' => 'Rimborso Totale',
                                'partial' => 'Rimborso Parziale',
                            ])
                            ->default('full')
                            ->required()
                            ->live(), // filament 3 uses live() instead of reactive()
                        Forms\Components\TextInput::make('amount')
                            ->label('Importo (EUR)')
                            ->numeric()
                            ->prefix('€')
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('refund_type') === 'partial')
                            ->maxValue(fn (Order $record) => (float) $record->total_price)
                            ->minValue(0.01)
                            ->step(0.01),
                    ])
                    ->modalHeading('Emetti Rimborso')
                    ->modalDescription('Il rimborso verrà emesso direttamente sul metodo di pagamento originale del cliente. Lo stock NON verrà modificato automaticamente.')
                    ->modalSubmitActionLabel('Emetti Rimborso')
                    ->action(function (Order $record, array $data): void {
                        try {
                            $service = match ($record->payment_gateway) {
                                PaymentGateway::Stripe => new StripePaymentService,
                                PaymentGateway::PayPal => new PayPalPaymentService,
                                default => throw new \Exception('Gateway non supportato per il rimborso automatico.'),
                            };

                            $amount = $data['refund_type'] === 'partial' ? (float) $data['amount'] : null;

                            $service->refund($record, $amount);

                            if ($data['refund_type'] === 'full') {
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

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Errore durante il rimborso')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 6. Scarica Ricevuta
                Tables\Actions\Action::make('download_receipt')
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

                // Standard view & edit
                ...static::viewAndEditActions(),
            ])
            ->bulkActions(static::softDeleteBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrderItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
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

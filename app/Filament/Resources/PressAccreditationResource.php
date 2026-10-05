<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PressAccreditationResource\Pages;
use App\Http\Controllers\PressAccreditationController;
use App\Mail\AccreditoStampaConfermato;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PressAccreditationResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    /**
     * Le etichette dello stato servono al form, al filtro e al badge della
     * tabella: tenerle in un posto solo evita che i tre elenchi divergano.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'unread' => 'In Attesa',
        'read' => 'Letto',
        'replied' => 'Accreditato / Risposto',
    ];

    protected static ?string $navigationLabel = 'Richieste Accrediti';

    protected static ?string $pluralLabel = 'Richieste Accrediti';

    protected static ?string $modelLabel = 'Accredito Stampa';

    protected static ?string $navigationGroup = 'Comunicazione';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'forms';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Accredito')
                    ->description('Visualizza i dettagli della richiesta di accredito stampa.')
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Richiedente')
                                    ->readOnly(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->readOnly(),
                            ]),
                        Forms\Components\TextInput::make('subject')
                            ->label('Oggetto/Tipo Richiesta')
                            ->readOnly(),
                        Forms\Components\Textarea::make('message')
                            ->label('Dettagli/Testata Giornalistica')
                            ->rows(5)
                            ->readOnly()
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(2),

                Forms\Components\Section::make('Stato & Gestione')
                    ->description('Gestisci l\'approvazione e lo stato dell\'accredito.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Stato')
                            ->options(self::STATUS_LABELS)
                            ->default('unread')
                            ->helperText('Da qui non parte nessuna email: per avvisare il richiedente usa «Accredita e avvisa» dall\'elenco.')
                            ->required(),
                        Forms\Components\Textarea::make('extra_data.admin_notes')
                            ->label('Note Amministratore')
                            ->rows(3)
                            ->placeholder('Es: Confermato pass tribuna stampa...'),
                        Forms\Components\Placeholder::make('conferma_inviata_il')
                            ->label('Conferma inviata il')
                            ->content(fn ($record): string => $record?->extra_data['conferma_inviata_il'] ?? '-'),
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Ricevuto il')
                            ->content(fn ($record): string => $record && $record->created_at ? $record->created_at->format('d/m/Y H:i:s') : '-'),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Richiedente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Oggetto')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'unread' => 'danger',
                        'read' => 'warning',
                        'replied' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ricevuto il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options(self::STATUS_LABELS),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        // Il modulo ha il solo campo `extra_data.admin_notes`: senza
                        // questa fusione il salvataggio riscriveva `extra_data` con
                        // la sola nota e cancellava ciò che il sito aveva raccolto
                        // (testata, ruolo, gara, telefono).
                        ->mutateFormDataUsing(function (array $data, $record): array {
                            $data['extra_data'] = array_merge(
                                is_array($record->extra_data) ? $record->extra_data : [],
                                is_array($data['extra_data'] ?? null) ? $data['extra_data'] : [],
                            );

                            return $data;
                        }),
                    Tables\Actions\Action::make('markAsRead')
                        ->label('Segna come Letto')
                        ->icon('heroicon-o-check-circle')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'unread')
                        ->action(fn ($record) => $record->update(['status' => 'read'])),
                    // Prima cambiava solo lo stato e il richiedente non sapeva
                    // niente: ora gli manda la conferma per email.
                    Tables\Actions\Action::make('markAsReplied')
                        ->label(fn ($record) => $record->status === 'replied' ? 'Reinvia conferma' : 'Accredita e avvisa')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->modalHeading('Conferma accredito')
                        ->modalDescription(fn ($record) => "Il richiedente riceverà la conferma all'indirizzo {$record->email}.")
                        ->modalSubmitActionLabel('Accredita e invia')
                        ->form([
                            Forms\Components\Textarea::make('messaggio')
                                ->label('Messaggio per il richiedente (facoltativo)')
                                ->helperText('Compare nell\'email: ritiro del pass, orari, ingresso.')
                                ->rows(4),
                        ])
                        ->action(fn ($record, array $data) => self::accredita($record, $data['messaggio'] ?? null)),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Segna la richiesta come accreditata e manda la conferma al richiedente.
     * Lo stato cambia anche se l'invio fallisce: la redazione lo vede dalla
     * notifica e può riprovare con «Reinvia conferma».
     */
    public static function accredita(ContactMessage $richiesta, ?string $messaggio = null): void
    {
        $richiesta->update(['status' => 'replied']);

        try {
            Mail::to($richiesta->email, $richiesta->name)->send(new AccreditoStampaConfermato($richiesta, $messaggio));
        } catch (\Throwable $e) {
            Log::error('Conferma accredito stampa non inviata', ['id' => $richiesta->id, 'error' => $e->getMessage()]);

            Notification::make()
                ->title('Accreditato, ma l\'email non è partita')
                ->body('Riprova con «Reinvia conferma» o scrivi al richiedente.')
                ->danger()
                ->send();

            return;
        }

        $richiesta->update(['extra_data' => [
            ...(is_array($richiesta->extra_data) ? $richiesta->extra_data : []),
            'conferma_inviata_il' => now()->format('d/m/Y H:i'),
        ]]);

        Notification::make()
            ->title("Conferma inviata a {$richiesta->email}")
            ->success()
            ->send();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('subject', PressAccreditationController::SUBJECT);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePressAccreditations::route('/'),
        ];
    }
}

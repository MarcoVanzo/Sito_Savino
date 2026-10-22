<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PressAccreditationResource\Pages\ManagePressAccreditations;
use App\Filament\Traits\TabellaMessaggiModulo;
use App\Http\Controllers\PressAccreditationController;
use App\Mail\AccreditoStampaConfermato;
use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PressAccreditationResource extends Resource
{
    use TabellaMessaggiModulo;

    protected static ?string $model = ContactMessage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

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

    protected static string|\UnitEnum|null $navigationGroup = 'Comunicazione';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'forms';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli Accredito')
                    ->description('Visualizza i dettagli della richiesta di accredito stampa.')
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Richiedente')
                                    ->readOnly(),
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->readOnly(),
                            ]),
                        TextInput::make('subject')
                            ->label('Oggetto/Tipo Richiesta')
                            ->readOnly(),
                        Textarea::make('message')
                            ->label('Dettagli/Testata Giornalistica')
                            ->rows(5)
                            ->readOnly()
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(2),

                Section::make('Stato & Gestione')
                    ->description('Gestisci l\'approvazione e lo stato dell\'accredito.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Select::make('status')
                            ->label('Stato')
                            ->options(self::STATUS_LABELS)
                            ->default('unread')
                            ->helperText('Da qui non parte nessuna email: per avvisare il richiedente usa «Accredita e avvisa» dall\'elenco.')
                            ->required(),
                        Textarea::make('extra_data.admin_notes')
                            ->label('Note Amministratore')
                            ->rows(3)
                            ->placeholder('Es: Confermato pass tribuna stampa...'),
                        Placeholder::make('conferma_inviata_il')
                            ->label('Conferma inviata il')
                            ->content(fn ($record): string => $record?->extra_data['conferma_inviata_il'] ?? '-'),
                        Placeholder::make('created_at')
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
            ->columns(self::colonneMessaggio('Richiedente'))
            ->defaultSort('created_at', 'desc')
            ->filters([
                self::filtroStato(),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::azioneModificaNote(),
                    self::azioneSegnaLetto(),
                    // Prima cambiava solo lo stato e il richiedente non sapeva
                    // niente: ora gli manda la conferma per email.
                    Action::make('markAsReplied')
                        ->label(fn ($record) => $record->status === 'replied' ? 'Reinvia conferma' : 'Accredita e avvisa')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->modalHeading('Conferma accredito')
                        ->modalDescription(fn ($record) => "Il richiedente riceverà la conferma all'indirizzo {$record->email}.")
                        ->modalSubmitActionLabel('Accredita e invia')
                        ->schema([
                            Textarea::make('messaggio')
                                ->label('Messaggio per il richiedente (facoltativo)')
                                ->helperText('Compare nell\'email: ritiro del pass, orari, ingresso.')
                                ->rows(4),
                        ])
                        ->action(fn ($record, array $data) => self::accredita($record, $data['messaggio'] ?? null)),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
        } catch (Throwable $e) {
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
            'index' => ManagePressAccreditations::route('/'),
        ];
    }
}

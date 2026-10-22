<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages\ManageContactMessages;
use App\Filament\Traits\TabellaMessaggiModulo;
use App\Http\Controllers\PressAccreditationController;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactMessageResource extends Resource
{
    use TabellaMessaggiModulo;

    protected static ?string $model = ContactMessage::class;

    /**
     * Le etichette dello stato servono al form, al filtro e al badge della
     * tabella: tenerle in un posto solo evita che i tre elenchi divergano.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'unread' => 'Non Letto',
        'read' => 'Letto',
        'replied' => 'Risposto',
    ];

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Messaggi Contatti';

    protected static ?string $modelLabel = 'Messaggio';

    protected static ?string $pluralModelLabel = 'Messaggi Contatti';

    protected static string|\UnitEnum|null $navigationGroup = 'Società';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'messaggi-contatti';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dettagli Messaggio')
                    ->description('Visualizza i dettagli del messaggio inviato dall\'utente dal modulo contatti.')
                    ->icon('heroicon-o-envelope')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nome Mittente')
                                    ->readOnly(),
                                TextInput::make('email')
                                    ->label('Email Mittente')
                                    ->email()
                                    ->readOnly(),
                            ]),
                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->readOnly(),
                        Textarea::make('message')
                            ->label('Messaggio')
                            ->rows(5)
                            ->readOnly()
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(2),

                Section::make('Stato & Gestione')
                    ->description('Aggiorna lo stato del messaggio per tenere traccia delle letture e risposte.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Select::make('status')
                            ->label('Stato Messaggio')
                            ->options(self::STATUS_LABELS)
                            ->default('unread')
                            ->required(),
                        Textarea::make('extra_data.admin_notes')
                            ->label('Note Amministratore')
                            ->rows(3)
                            ->placeholder('Es: Telefonato al cliente per dettagli...'),
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
            ->columns(self::colonneMessaggio('Nome Mittente'))
            ->defaultSort('created_at', 'desc')
            ->filters([
                self::filtroStato(),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::azioneModificaNote(),
                    self::azioneSegnaLetto(),
                    Action::make('markAsReplied')
                        ->label('Segna come Risposto')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->visible(fn ($record) => $record->status !== 'replied')
                        ->action(fn ($record) => $record->update(['status' => 'replied'])),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                $query->where('subject', '!=', PressAccreditationController::SUBJECT)
                    ->orWhereNull('subject');
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContactMessages::route('/'),
        ];
    }
}

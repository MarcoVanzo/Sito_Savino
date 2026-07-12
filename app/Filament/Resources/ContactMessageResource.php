<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Messaggi Contatti';

    protected static ?string $navigationGroup = 'Comunicazione';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'messaggi-contatti';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dettagli Messaggio')
                    ->description('Visualizza i dettagli del messaggio inviato dall\'utente dal modulo contatti.')
                    ->icon('heroicon-o-envelope')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome Mittente')
                                    ->readOnly(),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email Mittente')
                                    ->email()
                                    ->readOnly(),
                            ]),
                        Forms\Components\TextInput::make('subject')
                            ->label('Oggetto')
                            ->readOnly(),
                        Forms\Components\Textarea::make('message')
                            ->label('Messaggio')
                            ->rows(5)
                            ->readOnly()
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(2),

                Forms\Components\Section::make('Stato & Gestione')
                    ->description('Aggiorna lo stato del messaggio per tenere traccia delle letture e risposte.')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Stato Messaggio')
                            ->options([
                                'unread' => 'Non Letto',
                                'read' => 'Letto',
                                'replied' => 'Risposto',
                            ])
                            ->default('unread')
                            ->required(),
                        Forms\Components\Textarea::make('extra_data.admin_notes')
                            ->label('Note Amministratore')
                            ->rows(3)
                            ->placeholder('Es: Telefonato al cliente per dettagli...'),
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
                    ->label('Nome Mittente')
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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unread' => 'Non Letto',
                        'read' => 'Letto',
                        'replied' => 'Risposto',
                        default => $state,
                    })
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
                    ->options([
                        'unread' => 'Non Letto',
                        'read' => 'Letto',
                        'replied' => 'Risposto',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('markAsRead')
                        ->label('Segna come Letto')
                        ->icon('heroicon-o-check-circle')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'unread')
                        ->action(fn ($record) => $record->update(['status' => 'read'])),
                    Tables\Actions\Action::make('markAsReplied')
                        ->label('Segna come Risposto')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->visible(fn ($record) => $record->status !== 'replied')
                        ->action(fn ($record) => $record->update(['status' => 'replied'])),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageContactMessages::route('/'),
        ];
    }
}

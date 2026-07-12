<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PressAccreditationResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PressAccreditationResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

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
                            ->options([
                                'unread' => 'In Attesa',
                                'read' => 'Letto',
                                'replied' => 'Accreditato / Risposto',
                            ])
                            ->default('unread')
                            ->required(),
                        Forms\Components\Textarea::make('extra_data.admin_notes')
                            ->label('Note Amministratore')
                            ->rows(3)
                            ->placeholder('Es: Confermato pass tribuna stampa...'),
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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unread' => 'In Attesa',
                        'read' => 'Letto',
                        'replied' => 'Accreditato / Risposto',
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
                        'unread' => 'In Attesa',
                        'read' => 'Letto',
                        'replied' => 'Accreditato / Risposto',
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
                        ->label('Accredita / Rispondi')
                        ->icon('heroicon-o-check')
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('subject', 'Stampa / Media');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePressAccreditations::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterSubscriberResource\Pages;
use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Models\NewsletterSubscriber;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Newsletter';

    protected static ?string $modelLabel = 'Iscritto Newsletter';

    protected static ?string $pluralModelLabel = 'Iscritti Newsletter';

    protected static ?int $navigationSort = 10;

    /**
     * L'elenco vive dentro Analytics Newsletter: due voci di menu per la
     * stessa cosa facevano guardare i numeri e agire sui contatti in due
     * schermate diverse. La resource resta registrata — le sue rotte e i suoi
     * permessi servono ancora alla tabella incorporata nella pagina.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nome')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('synced_to_ac')
                    ->label('Sync AC')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ac_contact_id')
                    ->label('AC ID')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('source')
                    ->label('Fonte')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Iscritto il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unsubscribed_at')
                    ->label('Disiscritto il')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Attivo')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('subscribed_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('synced_to_ac')
                    ->label('Stato sincronizzazione')
                    ->placeholder('Tutti')
                    ->trueLabel('Sincronizzati')
                    ->falseLabel('Non sincronizzati'),
                Tables\Filters\Filter::make('active')
                    ->label('Solo attivi')
                    ->query(fn ($query) => $query->whereNull('unsubscribed_at'))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('retry_sync')
                    ->label('Risincronizza')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (NewsletterSubscriber $record) => ! $record->synced_to_ac)
                    ->requiresConfirmation()
                    ->action(function (NewsletterSubscriber $record) {
                        SyncNewsletterToActiveCampaign::dispatch($record);
                        Notification::make()->success()->title('Sincronizzazione avviata')->send();
                    }),
                Tables\Actions\Action::make('unsubscribe')
                    ->label('Disiscrivi')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (NewsletterSubscriber $record) => $record->isSubscribed())
                    ->requiresConfirmation()
                    ->modalDescription('Il contatto resta in archivio ma non riceverà più la newsletter. Viene disiscritto anche da ActiveCampaign.')
                    ->action(function (NewsletterSubscriber $record) {
                        $record->unsubscribe('cms');
                        Notification::make()->success()->title('Contatto disiscritto')->send();
                    }),
                Tables\Actions\Action::make('resubscribe')
                    ->label('Riattiva')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (NewsletterSubscriber $record) => ! $record->isSubscribed())
                    ->requiresConfirmation()
                    ->modalDescription('Riattiva l\'iscrizione solo se il contatto ha chiesto di tornare in lista: rimetterlo di sua iniziativa viola il consenso revocato.')
                    ->action(function (NewsletterSubscriber $record) {
                        $record->update([
                            'unsubscribed_at' => null,
                            'subscribed_at' => now(),
                            'synced_to_ac' => false,
                        ]);
                        SyncNewsletterToActiveCampaign::dispatch($record);
                        Notification::make()->success()->title('Iscrizione riattivata')->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Elimina')
                    ->modalDescription('Cancella definitivamente il contatto, qui e su ActiveCampaign. Usare per le richieste di cancellazione dei dati; per la sola uscita dalla lista serve "Disiscrivi".'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('retry_sync_bulk')
                    ->label('Risincronizza selezionati')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if (! $record->synced_to_ac) {
                                SyncNewsletterToActiveCampaign::dispatch($record);
                                $count++;
                            }
                        }
                        Notification::make()->success()->title("{$count} sincronizzazioni avviate")->send();
                    }),
                Tables\Actions\BulkAction::make('unsubscribe_bulk')
                    ->label('Disiscrivi selezionati')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->unsubscribe('cms')) {
                                $count++;
                            }
                        }
                        Notification::make()->success()->title("{$count} contatti disiscritti")->send();
                    }),
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Elimina selezionati')
                    ->modalDescription('Cancella definitivamente i contatti, qui e su ActiveCampaign.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletterSubscribers::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

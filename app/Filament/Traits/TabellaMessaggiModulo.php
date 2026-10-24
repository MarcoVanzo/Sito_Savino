<?php

namespace App\Filament\Traits;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

/**
 * Parti comuni delle tabelle dei messaggi arrivati dai moduli del sito
 * (contatti e accrediti stampa): stesse colonne, stesso filtro per stato,
 * stessa modifica delle note e stessa azione «Segna come Letto».
 * La classe che lo usa definisce la costante STATUS_LABELS.
 */
trait TabellaMessaggiModulo
{
    /**
     * @return array<int, TextColumn>
     */
    protected static function colonneMessaggio(string $etichettaNome): array
    {
        return [
            TextColumn::make('name')
                ->label($etichettaNome)
                ->searchable()
                ->sortable(),
            TextColumn::make('email')
                ->label('Email')
                ->searchable()
                ->sortable(),
            TextColumn::make('subject')
                ->label('Oggetto')
                ->searchable()
                ->limit(40),
            TextColumn::make('status')
                ->label('Stato')
                ->badge()
                ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                ->color(fn (string $state): string => match ($state) {
                    'unread' => 'danger',
                    'read' => 'warning',
                    'replied' => 'success',
                    default => 'gray',
                }),
            TextColumn::make('created_at')
                ->label('Ricevuto il')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ];
    }

    protected static function filtroStato(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label('Stato')
            ->options(self::STATUS_LABELS);
    }

    protected static function azioneModificaNote(): EditAction
    {
        return EditAction::make()
            // Il modulo ha il solo campo `extra_data.admin_notes`: senza
            // questa fusione il salvataggio riscriveva `extra_data` con
            // la sola nota e cancellava ciò che il sito aveva raccolto
            // (testata, ruolo, gara, telefono).
            ->mutateDataUsing(function (array $data, $record): array {
                $data['extra_data'] = array_merge(
                    is_array($record->extra_data) ? $record->extra_data : [],
                    is_array($data['extra_data'] ?? null) ? $data['extra_data'] : [],
                );

                return $data;
            });
    }

    protected static function azioneSegnaLetto(): Action
    {
        return Action::make('markAsRead')
            ->label('Segna come Letto')
            ->icon('heroicon-o-check-circle')
            ->color('warning')
            ->visible(fn ($record) => $record->status === 'unread')
            ->action(fn ($record) => $record->update(['status' => 'read']));
    }
}

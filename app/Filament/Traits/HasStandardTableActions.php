<?php

namespace App\Filament\Traits;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;

/**
 * Trait condiviso tra le risorse Filament per evitare duplicazione
 * di colonne timestamp e bulk-actions standard.
 */
trait HasStandardTableActions
{
    /**
     * Colonne created_at + updated_at nascoste per default.
     *
     * @return array<TextColumn>
     */
    protected static function timestampColumns(): array
    {
        return [
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * Bulk actions standard (solo cancellazione).
     *
     * @return array<BulkActionGroup>
     */
    protected static function standardBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
            ]),
        ];
    }

    /**
     * Bulk actions con soft-delete (cancella, ripristina, e opzionalmente forza cancellazione).
     *
     * @param  bool  $allowForceDelete  Se true, aggiunge ForceDeleteBulkAction (default: false)
     * @return array<BulkActionGroup>
     */
    protected static function softDeleteBulkActions(bool $allowForceDelete = false): array
    {
        $actions = [
            DeleteBulkAction::make(),
            RestoreBulkAction::make(),
        ];

        if ($allowForceDelete) {
            $actions[] = ForceDeleteBulkAction::make();
        }

        return [
            BulkActionGroup::make($actions),
        ];
    }

    /**
     * Azioni riga standard: View + Edit.
     *
     * @return array<Action>
     */
    protected static function viewAndEditActions(): array
    {
        return [
            ViewAction::make(),
            EditAction::make(),
        ];
    }
}

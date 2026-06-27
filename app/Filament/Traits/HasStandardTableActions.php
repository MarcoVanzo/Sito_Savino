<?php

namespace App\Filament\Traits;

use Filament\Tables;

/**
 * Trait condiviso tra le risorse Filament per evitare duplicazione
 * di colonne timestamp e bulk-actions standard.
 */
trait HasStandardTableActions
{
    /**
     * Colonne created_at + updated_at nascoste per default.
     *
     * @return array<Tables\Columns\TextColumn>
     */
    protected static function timestampColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updated_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * Bulk actions standard (solo cancellazione).
     *
     * @return array<Tables\Actions\BulkActionGroup>
     */
    protected static function standardBulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ];
    }

    /**
     * Bulk actions con soft-delete (cancella, ripristina, forza cancellazione).
     *
     * @return array<Tables\Actions\BulkActionGroup>
     */
    protected static function softDeleteBulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
            ]),
        ];
    }

    /**
     * Azioni riga standard: View + Edit.
     *
     * @return array<Tables\Actions\Action>
     */
    protected static function viewAndEditActions(): array
    {
        return [
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ];
    }
}

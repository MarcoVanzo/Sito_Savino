<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MenuItem $model): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MenuItem $model): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MenuItem $model): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Cancellazione in blocco (DeleteBulkAction): senza questo metodo
     * Filament considera l'azione permessa a chiunque veda la lista.
     */
    public function deleteAny(User $user): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Riordino della tabella (MenuItemResource usa `reorderable('sort_order')`).
     * Senza questo metodo Filament considera il riordino permesso a chiunque
     * veda la lista: l'ordine delle voci di menu è contenuto pubblicato, quindi
     * va legato al permesso di modifica, non a quello di lettura.
     */
    public function reorder(User $user): bool
    {
        return $user->role->canManageEditorial();
    }
}

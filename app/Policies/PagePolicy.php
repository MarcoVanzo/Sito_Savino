<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
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
    public function view(User $user, Page $model): bool
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
    public function update(User $user, Page $model): bool
    {
        return $user->role->isSuperAdmin()
            || ($user->role->canManageEditorial() && $model->author_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Page $model): bool
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
}

<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canViewSport();
    }

    public function view(User $user, Player $player): bool
    {
        return $user->role->canViewSport();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function update(User $user, Player $player): bool
    {
        return $user->role->canManageSport();
    }

    public function delete(User $user, Player $player): bool
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
     * Ripristino da soft-delete (RestoreBulkAction).
     */
    public function restore(User $user, Player $player): bool
    {
        return $user->role->canManageSport();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->canManageSport();
    }
}

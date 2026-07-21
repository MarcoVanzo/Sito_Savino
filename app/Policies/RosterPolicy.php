<?php

namespace App\Policies;

use App\Models\Roster;
use App\Models\User;

class RosterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function view(User $user, Roster $roster): bool
    {
        return $user->role->canManageSport();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function update(User $user, Roster $roster): bool
    {
        return $user->role->canManageSport();
    }

    public function delete(User $user, Roster $roster): bool
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

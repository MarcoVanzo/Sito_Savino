<?php

namespace App\Policies;

use App\Models\Sponsor;
use App\Models\User;

class SponsorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageSponsors();
    }

    public function view(User $user, Sponsor $sponsor): bool
    {
        return $user->role->canManageSponsors();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageSponsors();
    }

    public function update(User $user, Sponsor $sponsor): bool
    {
        return $user->role->canManageSponsors();
    }

    public function delete(User $user, Sponsor $sponsor): bool
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

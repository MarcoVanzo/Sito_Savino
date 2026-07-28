<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

class TeamPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSport';
    }

    /**
     * Il ripristino da soft-delete resta al Coord. Sportivo: è il rimedio a una
     * cancellazione, non una cancellazione.
     */
    public function restore(User $user, Team $team): bool
    {
        return $user->role->canManageSport();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->canManageSport();
    }
}

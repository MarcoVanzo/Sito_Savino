<?php

namespace App\Policies;

use App\Models\Season;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

class SeasonPolicy
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
    public function restore(User $user, Season $season): bool
    {
        return $user->role->canManageSport();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->canManageSport();
    }
}

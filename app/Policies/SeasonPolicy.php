<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Season;
use App\Models\User;

class SeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function view(User $user, Season $season): bool
    {
        return $user->role->canManageSport();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function update(User $user, Season $season): bool
    {
        return $user->role->canManageSport();
    }

    public function delete(User $user, Season $season): bool
    {
        return $user->role->isSuperAdmin();
    }
}

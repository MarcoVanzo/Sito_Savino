<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function view(User $user, Team $team): bool
    {
        return $user->role->canManageSport();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function update(User $user, Team $team): bool
    {
        return $user->role->canManageSport();
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->role->isSuperAdmin();
    }
}

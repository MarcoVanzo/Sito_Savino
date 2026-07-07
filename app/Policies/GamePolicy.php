<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canViewSport();
    }

    public function view(User $user, Game $game): bool
    {
        return $user->role->canViewSport();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function update(User $user, Game $game): bool
    {
        return $user->role->canManageSport();
    }

    public function delete(User $user, Game $game): bool
    {
        return $user->role->isSuperAdmin();
    }
}

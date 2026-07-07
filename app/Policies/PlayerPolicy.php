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
}

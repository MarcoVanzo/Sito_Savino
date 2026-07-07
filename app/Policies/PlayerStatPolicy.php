<?php

namespace App\Policies;

use App\Models\PlayerStat;
use App\Models\User;

class PlayerStatPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function view(User $user, PlayerStat $playerStat): bool
    {
        return $user->role->canManageSport();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageSport();
    }

    public function update(User $user, PlayerStat $playerStat): bool
    {
        return $user->role->canManageSport();
    }

    public function delete(User $user, PlayerStat $playerStat): bool
    {
        return $user->role->isSuperAdmin();
    }
}

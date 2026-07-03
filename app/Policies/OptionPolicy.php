<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Option;
use App\Models\User;

class OptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageSystem();
    }

    public function view(User $user, Option $option): bool
    {
        return $user->role->canManageSystem();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageSystem();
    }

    public function update(User $user, Option $option): bool
    {
        return $user->role->canManageSystem();
    }

    public function delete(User $user, Option $option): bool
    {
        return $user->role->canManageSystem();
    }
}

<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    public function view(User $user, Team $model): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    public function update(User $user, Team $model): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    public function delete(User $user, Team $model): bool
    {
        return $user->role === UserRole::Admin;
    }
}

<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Season;
use App\Models\User;

class SeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    public function view(User $user, Season $model): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Season $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Season $model): bool
    {
        return $user->role === UserRole::Admin;
    }
}

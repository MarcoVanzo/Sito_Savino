<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Game $model): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Game $model): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Editor]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Game $model): bool
    {
        return $user->role === UserRole::Admin;
    }
}

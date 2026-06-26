<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Page;
use App\Models\User;

class PagePolicy
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
    public function view(User $user, Page $model): bool
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
    public function update(User $user, Page $model): bool
    {
        return $user->role === UserRole::Admin || ($user->role === UserRole::Editor && $model->author_id === $user->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Page $model): bool
    {
        return $user->role === UserRole::Admin;
    }
}

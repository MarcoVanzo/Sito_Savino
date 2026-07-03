<?php

namespace App\Policies;

use App\Models\HeroSlide;
use App\Models\User;

class HeroSlidePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HeroSlide $model): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HeroSlide $model): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HeroSlide $model): bool
    {
        return $user->role->isSuperAdmin();
    }
}

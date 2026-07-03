<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

class ContactMessagePolicy
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
    public function view(User $user, ContactMessage $model): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can create models.
     * Messages are submitted from the public frontend only.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ContactMessage $model): bool
    {
        return $user->role->canManageEditorial();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ContactMessage $model): bool
    {
        return $user->role->isSuperAdmin();
    }
}

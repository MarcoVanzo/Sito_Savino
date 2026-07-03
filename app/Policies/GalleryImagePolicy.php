<?php

namespace App\Policies;

use App\Models\GalleryImage;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\HandlesAuthorization;

class GalleryImagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canViewMedia();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GalleryImage $galleryImage): bool
    {
        return $user->role->canViewMedia();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role->canManageMedia();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GalleryImage $galleryImage): bool
    {
        return $user->role->canManageMedia();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GalleryImage $galleryImage): bool
    {
        return $user->role->canManageMedia();
    }
}

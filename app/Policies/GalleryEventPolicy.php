<?php

namespace App\Policies;

use App\Models\GalleryEvent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GalleryEventPolicy
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
    public function view(User $user, GalleryEvent $galleryEvent): bool
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
    public function update(User $user, GalleryEvent $galleryEvent): bool
    {
        return $user->role->canManageMedia();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GalleryEvent $galleryEvent): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Cancellazione in blocco (DeleteBulkAction): senza questo metodo
     * Filament considera l'azione permessa a chiunque veda la lista.
     */
    public function deleteAny(User $user): bool
    {
        return $user->role->isSuperAdmin();
    }
}

<?php

namespace App\Policies;

use App\Models\GalleryImage;
use App\Models\User;
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

    /**
     * Cancellazione in blocco (DeleteBulkAction): senza questo metodo
     * Filament considera l'azione permessa a chiunque veda la lista.
     */
    public function deleteAny(User $user): bool
    {
        return $user->role->canManageMedia();
    }

    /**
     * Riordino della tabella (GalleryImageResource e GalleryImagesRelationManager
     * usano `reorderable('sort_order')`). Senza questo metodo Filament dà il
     * riordino per permesso a chiunque veda la lista: qui è determinante,
     * perché viewAny() è aperto a canViewMedia() (Coord. Sportivo incluso) che
     * NON deve poter cambiare l'ordine delle foto pubblicate.
     */
    public function reorder(User $user): bool
    {
        return $user->role->canManageMedia();
    }
}

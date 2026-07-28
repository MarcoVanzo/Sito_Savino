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

    /**
     * Cancellazione in blocco (DeleteBulkAction): senza questo metodo
     * Filament considera l'azione permessa a chiunque veda la lista.
     */
    public function deleteAny(User $user): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Riordino della tabella (HeroSlideResource e la tabella slide della
     * HomepageSettingsPage usano `reorderable('sort_order')`). Senza questo
     * metodo Filament considera il riordino permesso a chiunque veda la lista:
     * la sequenza dello slider è contenuto pubblicato in homepage, quindi va
     * legata al permesso di modifica, non a quello di lettura.
     */
    public function reorder(User $user): bool
    {
        return $user->role->canManageEditorial();
    }
}

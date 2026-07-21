<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->role->canManageShop();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->role->canManageShop();
    }

    public function delete(User $user, Product $product): bool
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
     * Ripristino da soft-delete (RestoreBulkAction).
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->role->canManageShop();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->canManageShop();
    }
}

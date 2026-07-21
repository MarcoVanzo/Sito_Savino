<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->role->canManageShop();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function update(User $user, Order $order): bool
    {
        return $user->role->canManageShop();
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    /**
     * Cancellazione in blocco (DeleteBulkAction): senza questo metodo
     * Filament considera l'azione permessa a chiunque veda la lista.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->role->isSuperAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->isSuperAdmin();
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}

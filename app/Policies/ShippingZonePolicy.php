<?php

namespace App\Policies;

use App\Models\ShippingZone;
use App\Models\User;

class ShippingZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function view(User $user, ShippingZone $shippingZone): bool
    {
        return $user->role->canManageShop();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function update(User $user, ShippingZone $shippingZone): bool
    {
        return $user->role->canManageShop();
    }

    public function delete(User $user, ShippingZone $shippingZone): bool
    {
        return $user->role->canManageShop();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canManageShop();
    }
}

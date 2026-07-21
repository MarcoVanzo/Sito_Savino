<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $user->role->canManageShop();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->role->canManageShop();
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->role->canManageShop();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function restore(User $user, Coupon $coupon): bool
    {
        return $user->role->isSuperAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->isSuperAdmin();
    }

    public function forceDelete(User $user, Coupon $coupon): bool
    {
        return false;
    }
}

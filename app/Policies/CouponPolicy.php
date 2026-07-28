<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

/**
 * Ritirare un coupon è lavoro ordinario dello shop, quindi la cancellazione
 * non è riservata al super admin. Il ripristino invece sì: un coupon tornato
 * attivo è denaro che esce, e deve passare da chi amministra.
 */
class CouponPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageShop';
    }

    protected function deleteAbility(): string
    {
        return 'canManageShop';
    }

    public function restore(User $user, Coupon $coupon): bool
    {
        return $user->role->isSuperAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Un coupon cancellato resta agganciato agli ordini che lo hanno usato:
     * la cancellazione definitiva è negata a chiunque, super admin incluso.
     */
    public function forceDelete(User $user, Coupon $coupon): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}

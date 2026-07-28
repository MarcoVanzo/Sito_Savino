<?php

namespace App\Policies;

use App\Models\Auction;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

class AuctionPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageShop';
    }

    /**
     * Ripristinare un'asta cancellata per errore è rimedio ordinario, non
     * un'operazione distruttiva: resta al Resp. Shop anche se la cancellazione
     * è riservata al super admin.
     */
    public function restore(User $user, Auction $auction): bool
    {
        return $user->role->canManageShop();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->canManageShop();
    }
}

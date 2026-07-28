<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

class ProductPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageShop';
    }

    /**
     * Il ripristino da soft-delete resta al Resp. Shop: è il rimedio a una
     * cancellazione, non una cancellazione.
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

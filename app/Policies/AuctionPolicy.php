<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Auction;
use App\Models\User;

class AuctionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function view(User $user, Auction $auction): bool
    {
        return $user->role->canManageShop();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function update(User $user, Auction $auction): bool
    {
        return $user->role->canManageShop();
    }

    public function delete(User $user, Auction $auction): bool
    {
        return $user->role->isSuperAdmin();
    }
}

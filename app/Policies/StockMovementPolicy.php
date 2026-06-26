<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    /**
     * Solo admin può gestire i movimenti di magazzino.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, StockMovement $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, StockMovement $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, StockMovement $model): bool
    {
        return $user->role === UserRole::Admin;
    }
}

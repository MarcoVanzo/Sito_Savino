<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    /**
     * Solo admin può gestire i movimenti di magazzino.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, StockMovement $model): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, StockMovement $model): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, StockMovement $model): bool
    {
        return $user->role === 'admin';
    }
}

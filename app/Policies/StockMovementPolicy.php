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

    /**
     * I movimenti di stock sono immutabili — nessuna modifica consentita.
     */
    public function update(User $user, StockMovement $model): bool
    {
        return false;
    }

    /**
     * I movimenti di stock sono immutabili — nessuna cancellazione consentita.
     */
    public function delete(User $user, StockMovement $model): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Option;
use App\Models\User;

class OptionPolicy
{
    /**
     * Solo admin può gestire le opzioni del sito.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Option $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Option $model): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Option $model): bool
    {
        return $user->role === UserRole::Admin;
    }
}

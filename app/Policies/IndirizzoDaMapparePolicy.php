<?php

namespace App\Policies;

use App\Models\IndirizzoDaMappare;
use App\Models\User;

/**
 * I «404 da mappare» li scrive il sito, non il pannello: da qui si leggono,
 * si trasformano in redirect (che passa da `ReindirizzamentoPolicy`) o si
 * scartano.
 */
class IndirizzoDaMapparePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageSystem();
    }

    public function view(User $user, IndirizzoDaMappare $indirizzo): bool
    {
        return $user->role->canManageSystem();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, IndirizzoDaMappare $indirizzo): bool
    {
        return false;
    }

    public function delete(User $user, IndirizzoDaMappare $indirizzo): bool
    {
        return $user->role->canManageSystem();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canManageSystem();
    }
}

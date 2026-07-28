<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

class ContactMessagePolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageEditorial';
    }

    /**
     * I messaggi arrivano dal form pubblico: dal pannello non si creano.
     */
    public function create(User $user): bool
    {
        return false;
    }
}

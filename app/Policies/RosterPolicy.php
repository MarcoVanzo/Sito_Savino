<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

class RosterPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSport';
    }
}

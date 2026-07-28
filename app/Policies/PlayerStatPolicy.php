<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

class PlayerStatPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSport';
    }
}

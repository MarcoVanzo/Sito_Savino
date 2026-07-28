<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

class CategoryPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageEditorial';
    }
}

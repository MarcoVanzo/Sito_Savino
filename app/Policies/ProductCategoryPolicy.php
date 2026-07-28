<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

class ProductCategoryPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageShop';
    }
}

<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

class SponsorPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSponsors';
    }
}

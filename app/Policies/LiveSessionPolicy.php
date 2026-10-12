<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * La diretta con sponsor di una gara la governa la comunicazione (pre-roll,
 * ciclo, regia dal palazzetto), anche se la gara in sé la modifica l'area
 * sportiva. Cancellarla resta al super admin: sparisce con la gara.
 */
class LiveSessionPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSponsors';
    }
}

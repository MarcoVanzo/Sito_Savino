<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * Le creatività sponsor della diretta le cura chi cura gli sponsor: la
 * comunicazione, non serve essere amministratori. Anche la cancellazione,
 * perché uno spot scaduto lo toglie chi l'ha caricato.
 */
class LiveCreativePolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSponsors';
    }

    protected function deleteAbility(): string
    {
        return 'canManageSponsors';
    }
}

<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * Le zone di spedizione sono configurazione ordinaria dello shop: anche la
 * cancellazione resta al Resp. Shop.
 */
class ShippingZonePolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageShop';
    }

    protected function deleteAbility(): string
    {
        return 'canManageShop';
    }
}

<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

/**
 * Gli ordini sono documenti contabili: si consultano e si aggiornano di stato,
 * ma non si cancellano dal pannello — né singolarmente, né in blocco, né in
 * modo definitivo. Resta possibile al super admin ripristinare un ordine
 * soft-deleted da codice o da una versione precedente del pannello.
 */
class OrderPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageShop';
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}

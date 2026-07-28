<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * StaffMemberResource, ManagementResource e YouthStaffResource usano
 * `reorderable('sort_order')`: l'ordine di visualizzazione è contenuto
 * pubblicato e segue il permesso di modifica (default del trait).
 */
class StaffMemberPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSport';
    }
}

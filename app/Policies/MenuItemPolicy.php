<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * L'ordine delle voci di menu è contenuto pubblicato: il riordino segue il
 * permesso di modifica (default del trait), non quello di lettura.
 */
class MenuItemPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageEditorial';
    }
}

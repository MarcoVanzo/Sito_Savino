<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * La sequenza dello slider è contenuto pubblicato in homepage: il riordino
 * segue il permesso di modifica (default del trait), non quello di lettura.
 */
class HeroSlidePolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageEditorial';
    }
}

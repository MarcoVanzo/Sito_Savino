<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * Gli eventi sono contenuto pubblicato in homepage, come le slide del hero:
 * li gestisce chi gestisce i contenuti editoriali.
 */
class EventoPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageEditorial';
    }
}

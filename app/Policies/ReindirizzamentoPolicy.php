<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * Un redirect sbagliato porta via un indirizzo vero del sito: li gestisce
 * solo chi amministra.
 */
class ReindirizzamentoPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSystem';
    }
}

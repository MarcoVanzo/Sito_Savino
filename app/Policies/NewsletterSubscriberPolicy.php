<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

/**
 * Gli iscritti alla newsletter sono dati personali: la lista resta a chi
 * gestisce la comunicazione. Senza policy Filament la mostrerebbe a qualunque
 * utente con accesso al pannello.
 */
class NewsletterSubscriberPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageEditorial';
    }

    /**
     * Gli iscritti arrivano dal form pubblico, non dal pannello.
     */
    public function create(User $user): bool
    {
        return false;
    }
}

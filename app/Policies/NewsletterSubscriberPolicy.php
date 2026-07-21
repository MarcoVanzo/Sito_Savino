<?php

namespace App\Policies;

use App\Models\NewsletterSubscriber;
use App\Models\User;

/**
 * Gli iscritti alla newsletter sono dati personali: la lista resta a chi
 * gestisce la comunicazione. Senza policy Filament la mostrerebbe a
 * qualunque utente con accesso al pannello.
 */
class NewsletterSubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageEditorial();
    }

    public function view(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->role->canManageEditorial();
    }

    // Gli iscritti arrivano dal form pubblico, non dal pannello.

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->role->canManageEditorial();
    }

    public function delete(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->role->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->isSuperAdmin();
    }
}

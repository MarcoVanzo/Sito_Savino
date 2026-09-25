<?php

namespace App\Policies;

use App\Models\RichiestaDiRecesso;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

/**
 * Le dichiarazioni di recesso le crea solo il cliente, dal sito, e non si
 * cancellano: sono la prova di quando il recesso e' arrivato, e da quella
 * data corrono i 14 giorni per il rimborso. Dal pannello si segnano come
 * gestite e si annotano.
 */
class RichiestaDiRecessoPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageShop';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, RichiestaDiRecesso $richiesta): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, RichiestaDiRecesso $richiesta): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}

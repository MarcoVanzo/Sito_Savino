<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

class PlayerPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSport';
    }

    /**
     * Le giocatrici si consultano anche da fuori l'area sportiva (es. Resp.
     * Comunicazione che scrive le news).
     */
    protected function viewAbility(): string
    {
        return 'canViewSport';
    }

    /**
     * Ripristinare una scheda cancellata per errore è rimedio ordinario del
     * Coord. Sportivo, non un'operazione distruttiva.
     */
    public function restore(User $user, Player $player): bool
    {
        return $user->role->canManageSport();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->canManageSport();
    }
}

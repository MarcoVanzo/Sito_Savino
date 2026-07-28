<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

class GamePolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageSport';
    }

    /**
     * Il calendario si consulta anche da fuori l'area sportiva (es. Resp.
     * Comunicazione che scrive la news della partita).
     */
    protected function viewAbility(): string
    {
        return 'canViewSport';
    }
}

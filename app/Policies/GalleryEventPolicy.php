<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

class GalleryEventPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageMedia';
    }

    /**
     * Le gallerie si consultano anche da chi non le gestisce (Coord. Sportivo).
     */
    protected function viewAbility(): string
    {
        return 'canViewMedia';
    }
}

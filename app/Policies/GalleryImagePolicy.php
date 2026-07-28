<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * A differenza delle altre risorse media la cancellazione non è riservata al
 * super admin: togliere una foto da una galleria è lavoro ordinario di
 * redazione.
 *
 * Il riordino (`reorderable('sort_order')` in GalleryImageResource e nel
 * GalleryImagesRelationManager) segue il permesso di modifica: qui è
 * determinante, perché la lettura è aperta a canViewMedia() — Coord. Sportivo
 * incluso — che non deve poter cambiare l'ordine delle foto pubblicate.
 */
class GalleryImagePolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageMedia';
    }

    protected function viewAbility(): string
    {
        return 'canViewMedia';
    }

    protected function deleteAbility(): string
    {
        return 'canManageMedia';
    }
}

<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesByRole;

/**
 * Le varianti sono esposte nel CMS dal VariantsRelationManager di
 * ProductResource: senza policy Filament autorizzerebbe creazione, modifica
 * (prezzo e giacenza incluse) e cancellazione a qualsiasi ruolo con accesso al
 * pannello, anche a chi lo shop non lo vede nemmeno.
 *
 * A differenza di ProductPolicy la cancellazione non è riservata al super
 * admin: aggiungere e togliere taglie o colori è lavoro ordinario di catalogo
 * del Resp. Shop. Resta superadmin-only l'eliminazione del prodotto padre.
 */
class ProductVariantPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageShop';
    }

    protected function deleteAbility(): string
    {
        return 'canManageShop';
    }
}

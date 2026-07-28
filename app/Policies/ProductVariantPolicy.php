<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

/**
 * Le varianti di prodotto sono esposte nel CMS dal VariantsRelationManager di
 * ProductResource. Senza questa policy Filament autorizzava creazione, modifica
 * (prezzo e giacenza incluse) e cancellazione a qualsiasi ruolo con accesso al
 * pannello, anche a chi ProductPolicy::viewAny() tiene fuori dallo shop.
 *
 * A differenza di ProductPolicy, la cancellazione non è riservata al super
 * admin: aggiungere e togliere taglie o colori è lavoro ordinario di catalogo
 * del Resp. Shop. Resta superadmin-only l'eliminazione del prodotto padre.
 */
class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function view(User $user, ProductVariant $productVariant): bool
    {
        return $user->role->canManageShop();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function update(User $user, ProductVariant $productVariant): bool
    {
        return $user->role->canManageShop();
    }

    public function delete(User $user, ProductVariant $productVariant): bool
    {
        return $user->role->canManageShop();
    }

    /**
     * Cancellazione in blocco (DeleteBulkAction): senza questo metodo
     * Filament considera l'azione permessa a chiunque veda la lista.
     */
    public function deleteAny(User $user): bool
    {
        return $user->role->canManageShop();
    }
}

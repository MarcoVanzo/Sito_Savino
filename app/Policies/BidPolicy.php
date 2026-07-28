<?php

namespace App\Policies;

use App\Models\Bid;
use App\Models\User;

/**
 * Le offerte d'asta sono esposte nel CMS dal BidsRelationManager di
 * AuctionResource. Senza questa policy Filament autorizzava ogni azione sul
 * relation manager a chiunque riuscisse ad aprire la scheda dell'asta.
 *
 * Le offerte sono un registro storico: si leggono e, al più, si invalidano
 * (Bid::invalidate(), che non passa dal mass assignment). Non si creano, non si
 * modificano e non si cancellano dal pannello, altrimenti l'esito di un'asta
 * diventerebbe riscrivibile a posteriori senza traccia.
 */
class BidPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function view(User $user, Bid $bid): bool
    {
        return $user->role->canManageShop();
    }

    /**
     * Le offerte nascono solo dal sito pubblico, mai dal CMS.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Importo e validità non si modificano a mano: per annullare un'offerta
     * si usa l'azione "Invalida", che tiene traccia di invalidated_at.
     */
    public function update(User $user, Bid $bid): bool
    {
        return false;
    }

    public function delete(User $user, Bid $bid): bool
    {
        return false;
    }

    /**
     * Cancellazione in blocco (DeleteBulkAction): senza questo metodo
     * Filament considera l'azione permessa a chiunque veda la lista.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;

/**
 * Le righe d'ordine sono esposte nel CMS dall'OrderItemsRelationManager di
 * OrderResource. Senza questa policy Filament autorizzava ogni azione a
 * chiunque riuscisse ad aprire la scheda dell'ordine.
 *
 * Vincolo aggiuntivo: su un ordine GIÀ PAGATO le righe diventano immutabili.
 * Prima un Resp. Shop poteva cambiare quantità o prezzo di un ordine incassato,
 * disallineando il totale dall'importo realmente riscosso dal gateway di
 * pagamento (e falsando la contabilità) senza alcun controllo.
 */
class OrderItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function view(User $user, OrderItem $orderItem): bool
    {
        return $user->role->canManageShop();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageShop();
    }

    public function update(User $user, OrderItem $orderItem): bool
    {
        return $user->role->canManageShop() && ! $this->orderIsPaid($orderItem);
    }

    public function delete(User $user, OrderItem $orderItem): bool
    {
        return $user->role->canManageShop() && ! $this->orderIsPaid($orderItem);
    }

    /**
     * Cancellazione in blocco (DeleteBulkAction): senza questo metodo
     * Filament considera l'azione permessa a chiunque veda la lista.
     *
     * Qui non abbiamo il singolo record, quindi non possiamo verificare che
     * l'ordine non sia pagato: la cancellazione massiva resta negata a tutti,
     * coerentemente con OrderPolicy::deleteAny(). Le righe di un ordine non
     * ancora pagato si eliminano una alla volta con delete().
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * L'ordine è già stato incassato?
     *
     * In assenza della relazione (record orfano) si considera bloccato:
     * meglio negare che consentire una modifica non verificabile.
     */
    private function orderIsPaid(OrderItem $orderItem): bool
    {
        return $orderItem->order?->paid_at !== null;
    }
}

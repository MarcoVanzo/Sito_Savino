<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

/**
 * Le righe d'ordine sono esposte nel CMS dall'OrderItemsRelationManager di
 * OrderResource: senza policy Filament autorizzerebbe ogni azione a chiunque
 * riesca ad aprire la scheda dell'ordine.
 *
 * Vincolo aggiuntivo: su un ordine GIÀ PAGATO le righe diventano immutabili.
 * Altrimenti un Resp. Shop potrebbe cambiare quantità o prezzo di un ordine
 * incassato, disallineando il totale dall'importo realmente riscosso dal
 * gateway di pagamento (e falsando la contabilità) senza alcun controllo.
 */
class OrderItemPolicy
{
    use AuthorizesByRole;

    protected function manageAbility(): string
    {
        return 'canManageShop';
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
     * Sulla cancellazione in blocco non abbiamo il singolo record, quindi non
     * possiamo verificare che l'ordine non sia pagato: resta negata a tutti,
     * coerentemente con OrderPolicy::deleteAny(). Le righe di un ordine non
     * ancora pagato si eliminano una alla volta.
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

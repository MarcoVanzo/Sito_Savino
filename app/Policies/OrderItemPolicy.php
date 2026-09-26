<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByRole;

/**
 * Le righe d'ordine sono esposte nel CMS dall'OrderItemsRelationManager di
 * OrderResource: senza policy Filament autorizzerebbe ogni azione a chiunque
 * riesca ad aprire la scheda dell'ordine.
 *
 * Vincolo aggiuntivo: le righe si toccano solo su un ordine ANCORA IN ATTESA
 * e mai pagato. Su un ordine incassato un Resp. Shop cambiava quantità o
 * prezzo disallineando il totale dall'importo riscosso dal gateway; su uno
 * spedito, annullato o rimborsato la riga non corrispondeva piu' alla merce
 * partita o rientrata. Il solo `paid_at` non bastava: un bonifico annullato o
 * un ordine segnato spedito a mano non ce l'hanno. Il pannello oggi le
 * mostra comunque in sola lettura (OrderItemsRelationManager).
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
        return $user->role->canManageShop() && $this->orderIsStillPending($orderItem);
    }

    public function delete(User $user, OrderItem $orderItem): bool
    {
        return $user->role->canManageShop() && $this->orderIsStillPending($orderItem);
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
     * L'ordine è ancora in attesa e senza incasso?
     *
     * In assenza della relazione (record orfano) si considera bloccato:
     * meglio negare che consentire una modifica non verificabile.
     */
    private function orderIsStillPending(OrderItem $orderItem): bool
    {
        $order = $orderItem->order;

        return $order instanceof Order
            && $order->status === OrderStatus::Pending
            && $order->paid_at === null
            && $order->payment_id === null;
    }
}

<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Notifications\Notification;

class AdminNotificationService
{
    /**
     * Notifica gli admin di un nuovo ordine ricevuto.
     */
    public function notifyNewOrder(Order $order): void
    {
        $customerName = $order->user?->name ?? $order->guest_name ?? 'Guest';

        $this->sendToAdmins(
            Notification::make()
                ->title("Nuovo ordine #{$order->order_number}")
                ->body("Nuovo ordine da {$customerName} — €".number_format($order->total_price, 2, ',', '.'))
                ->icon('heroicon-o-shopping-cart')
                ->iconColor('success')
        );
    }

    /**
     * Notifica gli admin della ricezione di un pagamento.
     */
    public function notifyPaymentReceived(Order $order): void
    {
        $gatewayLabel = $order->payment_gateway?->getLabel() ?? 'N/D';

        $this->sendToAdmins(
            Notification::make()
                ->title("Pagamento ricevuto #{$order->order_number}")
                ->body('€'.number_format($order->total_price, 2, ',', '.')." tramite {$gatewayLabel}")
                ->icon('heroicon-o-banknotes')
                ->iconColor('success')
        );
    }

    /**
     * Notifica gli admin di stock basso per un prodotto.
     */
    public function notifyLowStock(Product $product): void
    {
        $this->sendToAdmins(
            Notification::make()
                ->title("Stock basso: {$product->name}")
                ->body("Il prodotto ha solo {$product->stock} unità rimanenti.")
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('warning')
        );
    }

    /**
     * Notifica gli admin di un prodotto esaurito.
     */
    public function notifyOutOfStock(Product $product): void
    {
        $this->sendToAdmins(
            Notification::make()
                ->title("Prodotto esaurito: {$product->name}")
                ->body('Il prodotto non ha più scorte disponibili. Disattivalo o riassortiscilo.')
                ->icon('heroicon-o-x-circle')
                ->iconColor('danger')
        );
    }

    /**
     * Notifica gli amministratori che la sincronizzazione con la Lega continua
     * a fallire.
     *
     * Va solo ai Super Admin: è un guasto di integrazione, non una questione di
     * shop. Resta una notifica del pannello, come tutte le altre di questo
     * servizio, e non una mail: l'invio SMTP non è ancora configurato in
     * produzione, quindi una mail sarebbe un avviso che non arriva a nessuno.
     *
     * @param  int  $consecutiveFailures  giri a vuoto consecutivi
     * @param  string  $reason  ultimo errore incontrato
     */
    public function notifyLvfSyncFailing(int $consecutiveFailures, string $reason): void
    {
        $this->sendToAdmins(
            Notification::make()
                ->title('Sincronizzazione Lega non riuscita')
                ->body("Il calendario della Lega non si aggiorna da {$consecutiveFailures} tentativi consecutivi. Ultimo errore: {$reason}")
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('danger'),
            [UserRole::SuperAdmin->value],
        );
    }

    /**
     * Invia una notifica Filament agli utenti dei ruoli indicati; per
     * impostazione predefinita SuperAdmin e ShopManager.
     *
     * @param  list<string>|null  $roles
     */
    private function sendToAdmins(Notification $notification, ?array $roles = null): void
    {
        $admins = User::whereIn('role', $roles ?? [
            UserRole::SuperAdmin->value,
            UserRole::ShopManager->value,
        ])->get();

        foreach ($admins as $admin) {
            $notification->sendToDatabase($admin);
        }
    }
}

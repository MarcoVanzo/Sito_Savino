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
                ->body("Nuovo ordine da {$customerName} — €" . number_format($order->total_price, 2, ',', '.'))
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
                ->body("€" . number_format($order->total_price, 2, ',', '.') . " tramite {$gatewayLabel}")
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
     * Invia una notifica Filament a tutti gli utenti con ruolo SuperAdmin o ShopManager.
     */
    private function sendToAdmins(Notification $notification): void
    {
        $admins = User::whereIn('role', [
            UserRole::SuperAdmin->value,
            UserRole::ShopManager->value,
        ])->get();

        foreach ($admins as $admin) {
            $notification->sendToDatabase($admin);
        }
    }
}

<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class AdminNotificationService
{
    /**
     * Notifica gli admin di un nuovo ordine ricevuto.
     */
    public function notifyNewOrder(Order $order): void
    {
        $customerName = $order->user->name ?? $order->guest_name ?? 'Guest';

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
     * Segnala un ordine che richiede intervento umano su una questione di denaro.
     *
     * Finora `flagForManualReview()` scriveva una riga in `shop_events` e una
     * nota sull'ordine, e si fermava lì: un doppio incasso da rimborsare restava
     * visibile solo a chi fosse andato a cercarlo. Va sia ai Super Admin sia ai
     * gestori dello shop, perché è materia di entrambi.
     *
     * @param  string  $reason  codice del caso (`double_payment`, `stock_shortage`, …)
     */
    public function notifyPaymentNeedsReview(Order $order, string $reason, string $message): void
    {
        $this->sendToAdmins(
            Notification::make()
                ->title("Ordine #{$order->order_number} da verificare")
                ->body(Str::limit($message, 300))
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('danger'),
        );

        // Anche a Sentry: la notifica del pannello si vede solo entrando nel
        // pannello, e questi casi non possono aspettare il prossimo accesso.
        report(new RuntimeException(
            "Ordine #{$order->order_number} richiede revisione manuale ({$reason}): {$message}"
        ));
    }

    /**
     * Avvisa che un job in coda ha esaurito i tentativi.
     *
     * Un job fallito non lascia nessuna traccia visibile: finisce in
     * `failed_jobs`, una tabella che nessuno apre. Fra i job ci sono l'invio
     * delle conferme d'ordine e le email ai vincitori d'asta.
     *
     * @param  string  $jobName  classe del job
     * @param  string  $reason  messaggio dell'eccezione
     */
    public function notifyJobFailed(string $jobName, string $reason): void
    {
        $this->sendToAdmins(
            Notification::make()
                ->title('Job in coda fallito')
                ->body(class_basename($jobName).' ha esaurito i tentativi. '.Str::limit($reason, 200))
                ->icon('heroicon-o-x-circle')
                ->iconColor('danger'),
            [UserRole::SuperAdmin->value],
        );
    }

    /**
     * Avvisa che il pianificatore si è fermato.
     *
     * Non è un guasto che si vede guardando il sito: le pagine rispondono
     * normalmente. Si manifesta come aste che non si chiudono, stock di ordini
     * abbandonati che resta bloccato e calendario della Lega fermo — cioè come
     * una serie di stranezze scollegate, giorni dopo.
     *
     * @param  int|null  $secondsSinceLastBeat  null se non è mai partito
     */
    public function notifySchedulerStalled(?int $secondsSinceLastBeat): void
    {
        $when = $secondsSinceLastBeat === null
            ? 'Non è mai partito dall\'ultimo rilascio.'
            : 'Ultimo segno di vita '.round($secondsSinceLastBeat / 60).' minuti fa.';

        $this->sendToAdmins(
            Notification::make()
                ->title('Il pianificatore si è fermato')
                ->body($when.' Aste, sblocco degli ordini non pagati e sincronizzazione con la Lega sono fermi.')
                ->icon('heroicon-o-clock')
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

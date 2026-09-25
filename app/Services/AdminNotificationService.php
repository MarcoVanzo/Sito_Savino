<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminNotificationService
{
    public function __construct(
        private readonly AvvisoTecnico $avviso = new AvvisoTecnico,
    ) {}

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
     * shop. Resta nel solo pannello: un calendario fermo qualche ora non è
     * un'urgenza da email, a differenza dei casi che passano da AvvisoTecnico.
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

        // Il webhook chiama da dentro una transazione con la riga dell'ordine
        // bloccata: un invio lento terrebbe il lock e farebbe andare in
        // timeout l'altra strada della cattura (il ritorno del cliente, §17).
        // Fuori da una transazione afterCommit esegue subito.
        DB::afterCommit(fn () => $this->avviso->invia(
            "Ordine #{$order->order_number} da verificare",
            "L'ordine #{$order->order_number} richiede un intervento manuale ({$reason}).\n\n{$message}",
            "ordine-{$order->id}-{$reason}",
        ));
    }

    /**
     * Avvisa che un job in coda ha esaurito i tentativi.
     *
     * Un job fallito non lascia nessuna traccia visibile: finisce in
     * `failed_jobs`, una tabella che nessuno apre. Fra i job ci sono l'invio
     * delle conferme d'ordine e le email ai vincitori d'asta.
     *
     * Per email solo fuori dalla coda `ai`: l'analisi dei volti dipende da
     * CompreFace e si recupera da sola al giro orario di `gallery:analyze`,
     * mentre sulla coda `default` ci sono le email ai clienti.
     *
     * @param  string  $jobName  classe del job
     * @param  string  $reason  messaggio dell'eccezione
     * @param  string|null  $queue  coda da cui arriva il job
     */
    public function notifyJobFailed(string $jobName, string $reason, ?string $queue = null): void
    {
        if ($queue !== 'ai') {
            $this->avviso->invia(
                'Job in coda fallito: '.class_basename($jobName),
                class_basename($jobName)." ha esaurito i tentativi.\n\n".Str::limit($reason, 1000)
                    ."\n\nI dettagli sono su Sentry; il job resta in failed_jobs (php artisan queue:retry).",
                'job-fallito:'.Str::slug(str_replace('\\', '-', $jobName)),
            );
        }

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

        // Il silenziatore orario sta già in VerifyApplicationHealth: finché il
        // guasto dura arriva una email all'ora, che fa anche da promemoria.
        $this->avviso->invia(
            'Il pianificatore si è fermato',
            $when.' Aste, sblocco degli ordini non pagati, sorveglianza dello shop e sincronizzazione con la Lega sono fermi.'
                ."\n\nSi riavvia il componente `scheduler` da DigitalOcean.",
            'pianificatore-fermo',
            0,
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

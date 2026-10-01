<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePaymentService implements PaymentGatewayInterface
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = self::client();
    }

    /**
     * Il client Stripe del sito.
     *
     * Un'istanza, non la chiave globale di `Stripe::setApiKey`, e con i
     * tentativi di rete: l'SDK ripete una POST caduta per strada con la
     * stessa chiave di idempotenza, quindi un timeout non apre due sessioni
     * e non emette due rimborsi. La versione dell'API e' quella fissata
     * dall'SDK: cambia solo aggiornando `stripe/stripe-php`.
     */
    public static function client(): StripeClient
    {
        return new StripeClient([
            'api_key' => (string) config('services.stripe.secret'),
            'max_network_retries' => 2,
        ]);
    }

    /**
     * Create a Stripe Checkout Session and return the redirect URL.
     */
    public function createSession(Order $order): string
    {
        // Niente `payment_method_types`: i metodi si accendono dal pannello di
        // Stripe (carte, Apple Pay, Google Pay, Klarna…). Quelli a esito
        // differito chiudono la sessione senza incasso: vedi
        // handleSessionCompleted.
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => (int) round($order->total_price * 100),
                        'product_data' => [
                            'name' => "Ordine {$order->order_number}",
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'customer_email' => $order->user->email ?? $order->guest_email,
            // Stripe accetta solo stringhe nei metadata: passare interi fa
            // rifiutare la chiamata.
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
            ],
            'success_url' => $order->successUrl().'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $order->cancelUrl(),
            ...$this->scadenza($order),
        ]);

        return $session->url;
    }

    /** Minuti di vita della sessione di un ordine dello shop. */
    public const DURATA_SESSIONE_SHOP_MINUTI = 45;

    /**
     * Quando scade la sessione di pagamento.
     *
     * Quella predefinita di Stripe dura 24 ore. Per lo shop era un buco:
     * `order:check-unpaid` annulla dopo un'ora l'ordine non pagato e rimette
     * la merce a scaffale, ma la sessione restava pagabile per un giorno, e il
     * cliente che pagava tardi finiva su un ordine annullato (merce forse gia'
     * rivenduta, rimborso a mano). 45 minuti stanno sotto l'ora
     * dell'annullamento e sopra il minimo di 30 accettato da Stripe.
     *
     * La sessione di un'asta scade invece col termine del vincitore:
     * aperta poco prima del termine, restava pagabile per un giorno intero
     * dopo che l'asta era passata al secondo offerente. Stripe accetta una
     * scadenza fra 30 minuti e 24 ore da adesso; sotto il minimo il pagamento
     * tardivo lo ferma il webhook (HandlesPaymentWebhooks::astaPassataAdAltri).
     *
     * @return array{expires_at?: int}
     */
    private function scadenza(Order $order): array
    {
        if ($order->auction_id === null) {
            return ['expires_at' => now()->addMinutes(self::DURATA_SESSIONE_SHOP_MINUTI)->getTimestamp()];
        }

        $termine = $order->auction?->winner_checkout_deadline;

        if ($termine === null) {
            return [];
        }

        $minimo = now()->addMinutes(31)->getTimestamp();
        $massimo = now()->addHours(24)->subMinute()->getTimestamp();

        return ['expires_at' => max($minimo, min($massimo, $termine->getTimestamp()))];
    }

    /**
     * Verify Stripe webhook signature and process the event.
     *
     * @param  string  $rawBody  Raw request body
     * @param  array  $headers  Request headers (must contain 'Stripe-Signature')
     * @return array{payment_id?: string, status: string, order_id?: int}
     *
     * @throws SignatureVerificationException
     */
    public function handleWebhook(string $rawBody, array $headers): array
    {
        $sigHeader = $headers['Stripe-Signature'] ?? $headers['stripe-signature'] ?? '';
        $webhookSecret = config('services.stripe.webhook_secret');

        // This will throw SignatureVerificationException if verification fails
        $event = Webhook::constructEvent($rawBody, $sigHeader, $webhookSecret);

        return match ($event->type) {
            'checkout.session.completed' => $this->handleSessionCompleted($event),
            'checkout.session.async_payment_succeeded' => $this->sessioneDiPagamento($event->data->object, 'completed'),
            'checkout.session.async_payment_failed' => $this->sessioneDiPagamento($event->data->object, 'denied'),
            'charge.refunded' => $this->handleChargeRefunded($event),
            'charge.dispute.created' => $this->contestazione($event),
            default => ['status' => 'ignored'],
        };
    }

    /**
     * Issue a full or partial refund via Stripe.
     */
    public function refund(Order $order, ?float $amount = null): bool
    {
        $payload = [
            'payment_intent' => $order->payment_id,
        ];

        if ($amount !== null && $amount > 0) {
            $payload['amount'] = (int) round($amount * 100);
        }

        // Un doppio clic sul pulsante del pannello mandava due rimborsi. La
        // chiave vale un minuto: il clic ripetuto si ferma, un secondo
        // rimborso voluto o un nuovo tentativo dopo un rifiuto passano (Stripe
        // conserva anche gli errori sotto la stessa chiave, per 24 ore). Il
        // gia' rimborsato nella chiave separa due parziali voluti anche nello
        // stesso minuto, una volta arrivato il webhook del primo.
        $giaRimborsato = (int) round(((float) $order->refunded_amount) * 100);
        $chiave = 'rimborso-'.$order->id.'-'.($payload['amount'] ?? 'totale').'-'.$giaRimborsato.'-'.intdiv(now()->getTimestamp(), 60);

        $this->stripe->refunds->create($payload, ['idempotency_key' => $chiave]);

        Log::info('Stripe refund emesso', [
            'order_id' => $order->id,
            'payment_id' => $order->payment_id,
            'amount' => $amount ?? 'total',
        ]);

        return true;
    }

    /**
     * Handle checkout.session.completed event.
     * Differenzia tra sessioni di pagamento e sessioni di setup (verifica carta).
     */
    private function handleSessionCompleted(Event $event): array
    {
        // StripeObject espone i campi anche come array: l'accesso a proprietà
        // dinamiche non è verificabile staticamente, questo sì.
        $session = $event->data->object;

        // Setup session (verifica metodo di pagamento per aste)
        if ($session['mode'] === 'setup') {
            return [
                'status' => 'setup_completed',
                'user_id' => (int) ($session['metadata']['user_id'] ?? 0),
            ];
        }

        // Sessione chiusa non vuol dire pagata: con un metodo a esito
        // differito (SEPA, bonifico) arriva `unpaid`, e l'incasso — o il
        // fallimento — arriva dopo con `async_payment_succeeded` / `_failed`.
        // Confermare adesso spediva merce non pagata.
        $pagata = in_array($session['payment_status'] ?? null, ['paid', 'no_payment_required'], true);

        return $this->sessioneDiPagamento($session, $pagata ? 'completed' : 'pending');
    }

    /**
     * Il risultato di una sessione di pagamento (ordine dello shop o d'asta).
     *
     * `pending` lascia l'ordine in attesa senza registrare la transazione,
     * `denied` lo annulla: sono gli stessi esiti delle catture PayPal
     * (HandlesPendingCaptures).
     */
    private function sessioneDiPagamento(mixed $session, string $stato): array
    {
        return [
            'payment_id' => $session['payment_intent'],
            'status' => $stato,
            'capture_status' => $session['payment_status'] ?? null,
            'order_id' => (int) ($session['metadata']['order_id'] ?? 0),
            // Gli importi di Stripe sono in centesimi. Serve al confronto col
            // totale dell'ordine: una sessione aperta su un carrello poi
            // cambiato incassa la cifra vecchia.
            'amount' => isset($session['amount_total']) ? ((int) $session['amount_total']) / 100 : null,
        ];
    }

    /**
     * Handle charge.refunded event.
     *
     * Stripe manda `charge.refunded` a OGNI rimborso, anche parziale: trattarlo
     * come totale rimetteva in giacenza merce ancora dal cliente e gli
     * annunciava un rimborso completo per le sole spese di spedizione. Il
     * charge porta l'importo rimborsato cumulato (`amount_refunded`) e il
     * flag `refunded`, vero solo a rimborso completo: si passano entrambi e
     * decide il trait.
     */
    private function handleChargeRefunded(Event $event): array
    {
        $charge = $event->data->object;

        $importo = isset($charge['amount']) ? (int) $charge['amount'] : null;
        $rimborsato = isset($charge['amount_refunded']) ? (int) $charge['amount_refunded'] : null;

        return [
            'payment_id' => $charge['payment_intent'],
            'status' => 'refunded',
            'totale' => ($charge['refunded'] ?? false) === true
                || ($importo !== null && $rimborsato !== null && $rimborsato >= $importo),
            // In euro, come il resto dei conti dell'ordine.
            'refunded_amount' => $rimborsato !== null ? $rimborsato / 100 : null,
        ];
    }

    /**
     * Handle charge.dispute.created: il cliente ha contestato l'addebito.
     *
     * La contestazione ha un termine per rispondere con le prove (di solito
     * una settimana o poco piu'): senza risposta Stripe la da' vinta al
     * cliente e trattiene importo e commissione.
     */
    private function contestazione(Event $event): array
    {
        $disputa = $event->data->object;
        $scadenza = $disputa['evidence_details']['due_by'] ?? null;

        return [
            'status' => 'dispute',
            'payment_id' => $disputa['payment_intent'] ?? null,
            'dispute_id' => $disputa['id'] ?? null,
            'reason' => $disputa['reason'] ?? null,
            'amount' => isset($disputa['amount']) ? ((int) $disputa['amount']) / 100 : null,
            'due_by' => is_numeric($scadenza) ? (int) $scadenza : null,
        ];
    }
}

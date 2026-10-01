<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayPalPaymentService implements PaymentGatewayInterface
{
    /**
     * Get the PayPal API base URL based on environment mode.
     */
    private function getBaseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Obtain and cache a PayPal OAuth2 access token.
     */
    private function getAccessToken(): string
    {
        return Cache::get($this->chiaveDelToken()) ?? $this->refreshAccessToken();
    }

    /**
     * La chiave porta la modalita': passando da sandbox a live (o viceversa) il
     * token di prima resterebbe in cache per ore e ogni chiamata al gateway
     * tornerebbe 401, senza che si capisca perche'.
     */
    private function chiaveDelToken(): string
    {
        return 'paypal_access_token:'.(config('services.paypal.mode') === 'live' ? 'live' : 'sandbox');
    }

    /**
     * Request a fresh OAuth token and cache it respecting PayPal's expires_in.
     */
    private function refreshAccessToken(): string
    {
        $response = Http::withBasicAuth(
            config('services.paypal.client_id'),
            config('services.paypal.client_secret'),
        )
            ->asForm()
            ->post("{$this->getBaseUrl()}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::error('PayPal OAuth token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new PayPalException('Impossibile ottenere il token PayPal');
        }

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in') ?? 3600; // seconds
        $cacheFor = max(60, $expiresIn - 300); // 5 minutes buffer

        Cache::put($this->chiaveDelToken(), $token, $cacheFor);

        return $token;
    }

    /**
     * Make an authenticated HTTP client instance.
     */
    private function client(): PendingRequest
    {
        return Http::withToken($this->getAccessToken())
            ->baseUrl($this->getBaseUrl())
            ->acceptJson()
            ->asJson();
    }

    /**
     * Create a PayPal order and return the approval redirect URL.
     */
    public function createSession(Order $order): string
    {
        $response = $this->client()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => number_format((float) $order->total_price, 2, '.', ''),
                    ],
                    'description' => "Ordine {$order->order_number} — Savino Del Bene Volley",
                ],
            ],
            'application_context' => [
                'brand_name' => 'Savino Del Bene Volley',
                'user_action' => 'PAY_NOW',
                'return_url' => $order->successUrl(),
                'cancel_url' => $order->cancelUrl(),
            ],
        ]);

        if ($response->failed()) {
            Log::error('PayPal create order failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new PayPalException('Impossibile creare l\'ordine PayPal');
        }

        $data = $response->json();

        // Find the HATEOAS 'approve' link
        $approveLink = collect($data['links'] ?? [])
            ->firstWhere('rel', 'approve');

        if (! $approveLink) {
            throw new PayPalException('PayPal approve link non trovato nella risposta');
        }

        return $approveLink['href'];
    }

    /**
     * Verify PayPal webhook signature and process the event.
     */
    public function handleWebhook(string $rawBody, array $headers): array
    {
        $payload = json_decode($rawBody, true);

        // Un corpo vuoto o non JSON non e' una notifica di PayPal: va
        // rifiutato come una firma sbagliata (400 e warning), non lasciato
        // arrivare alla verifica come TypeError, che finirebbe fra gli errori.
        if (! is_array($payload)) {
            throw new PayPalException('Corpo del webhook PayPal non valido');
        }

        // Verify webhook signature
        $this->verifyWebhookSignature($rawBody, $headers);

        $eventType = $payload['event_type'] ?? '';

        return match ($eventType) {
            'CHECKOUT.ORDER.APPROVED' => $this->handleOrderApproved($payload),
            // Una cattura rimasta PENDING (verifica antifrode, eCheck) si
            // chiude piu' tardi con uno di questi due eventi.
            'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($payload),
            'PAYMENT.CAPTURE.DENIED' => $this->handleCaptureDenied($payload),
            'PAYMENT.CAPTURE.REFUNDED' => $this->handleCaptureRefunded($payload),
            default => ['status' => 'ignored'],
        };
    }

    /**
     * Issue a full or partial refund for a captured PayPal payment.
     */
    public function refund(Order $order, ?float $amount = null): bool
    {
        $payload = [
            'note_to_payer' => 'Rimborso ordine Savino Del Bene Volley',
        ];

        if ($amount !== null && $amount > 0) {
            $payload['amount'] = [
                'value' => number_format((float) $amount, 2, '.', ''),
                'currency_code' => 'EUR',
            ];
        }

        $response = $this->client()->post("/v2/payments/captures/{$order->payment_id}/refund", $payload);

        if ($response->failed()) {
            Log::error('PayPal refund failed', [
                'order_id' => $order->id,
                'capture_id' => $order->payment_id,
                'amount' => $amount ?? 'total',
                'body' => $response->json(),
            ]);
            throw new PayPalException("Rimborso PayPal fallito per ordine {$order->order_number}");
        }

        Log::info('PayPal refund emesso', [
            'order_id' => $order->id,
            'capture_id' => $order->payment_id,
            'amount' => $amount ?? 'total',
        ]);

        return true;
    }

    /**
     * Verify PayPal webhook signature via the PayPal API.
     *
     * La firma copre il CRC32 del corpo cosi' come e' arrivato, quindi
     * `webhook_event` deve essere quel corpo, byte per byte. Decodificato in
     * array e ricodificato non lo e' piu': un oggetto vuoto `{}` diventa
     * `[]`. Il 01/10/2026 la notifica dell'ordine ORD-2026-00003, che di
     * oggetti vuoti ne contiene uno, e' stata rifiutata a ogni tentativo.
     *
     * @throws RuntimeException if verification fails
     */
    private function verifyWebhookSignature(string $rawBody, array $headers): void
    {
        $campi = json_encode([
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['PAYPAL-CERT-URL'] ?? $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => config('services.paypal.webhook_id'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $corpo = substr($campi, 0, -1).',"webhook_event":'.trim($rawBody).'}';

        $response = $this->client()
            ->withBody($corpo, 'application/json')
            ->post('/v1/notifications/verify-webhook-signature');

        if ($response->failed()) {
            // `error` e non `warning`: in produzione il livello dei log e'
            // `error`, e una verifica che non parte significa nessun incasso
            // dal webhook — va vista.
            Log::error('PayPal: la verifica della firma non ha risposto', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new PayPalException('Verifica firma webhook PayPal fallita');
        }

        $status = $response->json('verification_status');

        if ($status !== 'SUCCESS') {
            Log::error('PayPal: firma del webhook non valida', [
                'verification_status' => $status,
                'webhook_id_configurato' => config('services.paypal.webhook_id'),
            ]);
            throw new PayPalException('Firma webhook PayPal non valida');
        }
    }

    /**
     * Handle CHECKOUT.ORDER.APPROVED: capture the payment.
     */
    private function handleOrderApproved(array $payload): array
    {
        $paypalOrderId = $payload['resource']['id'] ?? null;

        if (! $paypalOrderId) {
            throw new PayPalException('PayPal order ID non trovato nel payload webhook');
        }

        $ordineRemoto = $payload['resource'] ?? [];

        // Approvare non e' pagare: finche' non si cattura il denaro resta al
        // cliente. Un ordine locale gia' annullato (checkout abbandonato dopo
        // un'ora, turno dell'asta scaduto) ha la merce tornata a scaffale, e
        // catturare adesso significava incassare per poi dover rimborsare a
        // mano. Se l'evento dice di quale ordine si tratta e quell'ordine non
        // e' piu' in attesa, non si cattura: l'approvazione scade da sola.
        $locale = $this->ordineLocaleDellEvento($ordineRemoto);

        if ($locale !== null && ! $this->ordineAncoraDaIncassare($locale)) {
            Log::warning('PayPal: ordine approvato ma non piu\' in attesa, cattura non eseguita', [
                'paypal_order_id' => $paypalOrderId,
                'order_id' => $locale->id,
                'status' => $locale->status->value,
            ]);

            return ['status' => 'ignored'];
        }

        // L'ordine così come sta nell'evento: è la fonte più affidabile del
        // custom_id, perché è la copia di quello che abbiamo inviato noi.
        return $this->catturaEDescrivi($paypalOrderId, $ordineRemoto);
    }

    /**
     * L'ordine locale indicato dall'ordine PayPal dell'evento, se l'evento lo
     * dice (custom_id o reference_id della prima unita' d'acquisto).
     *
     * @param  array<string, mixed>  $ordineRemoto
     */
    private function ordineLocaleDellEvento(array $ordineRemoto): ?Order
    {
        $unita = $ordineRemoto['purchase_units'][0] ?? [];
        $customId = $unita['custom_id'] ?? null;

        if (is_numeric($customId)) {
            return Order::find((int) $customId);
        }

        $numero = $unita['reference_id'] ?? null;

        return $numero ? Order::where('order_number', $numero)->first() : null;
    }

    /**
     * Si puo' ancora catturare per questo ordine? Solo se e' in attesa e
     * senza una transazione gia' registrata. Riletto dal database, non dalla
     * copia in memoria: l'annullamento automatico puo' essere passato un
     * attimo fa.
     */
    private function ordineAncoraDaIncassare(Order $order): bool
    {
        $attuale = Order::query()->whereKey($order->id)->first(['id', 'status', 'payment_id']);

        return $attuale !== null
            && $attuale->status === OrderStatus::Pending
            && $attuale->payment_id === null;
    }

    /**
     * Cattura l'ordine approvato e restituisce l'esito nel formato atteso dal
     * trait dei webhook (payment_id, status, order_id).
     *
     * @param  array<string, mixed>  $ordineRemoto  rappresentazione dell'ordine già in nostro possesso
     * @return array{payment_id: string, status: string, capture_status: string|null, order_id: int, amount: float|null}
     */
    private function catturaEDescrivi(string $paypalOrderId, array $ordineRemoto = []): array
    {
        // Il corpo va mandato come oggetto vuoto: senza dati Laravel codifica
        // un array vuoto, cioè "[]", che PayPal live rifiuta con
        // MALFORMED_REQUEST_JSON (la sandbox lo accettava). Il primo ordine
        // vero, l'1/10/2026, è rimasto approvato e mai incassato per questo.
        $response = $this->client()
            ->withBody('{}', 'application/json')
            ->post("/v2/checkout/orders/{$paypalOrderId}/capture");

        if ($response->failed()) {
            // Un ordine già catturato non è un guasto: è il secondo tentativo
            // sullo stesso pagamento (webhook ripetuto, o webhook e ritorno dal
            // browser insieme). Si rilegge l'ordine e si prosegue: a valle
            // l'idempotenza sulla coppia (ordine, payment_id) fa il resto.
            if ($this->eGiaCatturato($response->json())) {
                Log::info('PayPal: ordine già catturato, rileggo la transazione', [
                    'paypal_order_id' => $paypalOrderId,
                ]);

                $ordine = $this->leggiOrdineRemoto($paypalOrderId);

                if ($ordine === null) {
                    throw new PayPalException('Ordine PayPal già catturato ma non rileggibile');
                }

                return $this->esitoDa($paypalOrderId, $ordine, $ordineRemoto);
            }

            Log::error('PayPal capture failed', [
                'paypal_order_id' => $paypalOrderId,
                'body' => $response->json(),
            ]);
            throw new PayPalException('Cattura pagamento PayPal fallita');
        }

        return $this->esitoDa($paypalOrderId, (array) $response->json(), $ordineRemoto);
    }

    /**
     * Ricava identificativo della transazione e ordine locale dalla risposta di
     * cattura, con l'ordine dell'evento come riserva.
     *
     * @param  array<string, mixed>  $risposta  risposta della cattura (o rilettura dell'ordine)
     * @param  array<string, mixed>  $ordineRemoto  ordine come arrivato nell'evento
     * @return array{payment_id: string, status: string, capture_status: string|null, order_id: int, amount: float|null}
     */
    private function esitoDa(string $paypalOrderId, array $risposta, array $ordineRemoto = []): array
    {
        $unita = $risposta['purchase_units'][0] ?? [];
        $cattura = $unita['payments']['captures'][0] ?? [];

        // `custom_id` è dichiarato sia sull'unità d'acquisto sia sulla singola
        // cattura, ma la risposta della cattura non è tenuta a riportarlo:
        // prendendolo solo da lì, un pagamento incassato finiva su `order_id`
        // zero, cioè su un ordine che non esiste — soldi presi e ordine mai
        // confermato. Si guarda anche l'ordine dell'evento e, in ultima
        // istanza, il numero d'ordine passato come reference_id.
        $unitaEvento = $ordineRemoto['purchase_units'][0] ?? [];

        $customId = $cattura['custom_id']
            ?? $unita['custom_id']
            ?? $unitaEvento['custom_id']
            ?? null;

        $orderId = is_numeric($customId) ? (int) $customId : 0;

        if ($orderId === 0) {
            $numero = $unita['reference_id'] ?? $unitaEvento['reference_id'] ?? null;
            $orderId = $numero ? (int) (Order::where('order_number', $numero)->value('id') ?? 0) : 0;

            Log::warning('PayPal: custom_id assente nella risposta, ordine risolto dal numero', [
                'paypal_order_id' => $paypalOrderId,
                'reference_id' => $numero,
                'order_id' => $orderId,
            ]);
        }

        // Solo una cattura COMPLETED e' denaro incassato. PENDING (verifica
        // antifrode, eCheck, conto del venditore da confermare) puo' ancora
        // finire DENIED: registrarla come pagata confermava l'ordine e
        // mandava la merce a chi poteva non pagarla mai. La conclusione
        // arriva dopo, con PAYMENT.CAPTURE.COMPLETED o .DENIED.
        $statoCattura = $cattura['status'] ?? null;

        return [
            'payment_id' => $cattura['id'] ?? $paypalOrderId,
            'status' => $statoCattura === 'COMPLETED' ? 'completed' : 'pending',
            'capture_status' => is_string($statoCattura) ? $statoCattura : null,
            'order_id' => $orderId,
            // Quanto il gateway dice di aver incassato: il confronto col totale
            // dell'ordine e' l'unico modo per accorgersi che il cliente ha
            // pagato una sessione aperta su un carrello poi cambiato.
            'amount' => isset($cattura['amount']['value']) ? (float) $cattura['amount']['value'] : null,
        ];
    }

    /**
     * Riconosce la risposta d'errore di un ordine già catturato.
     *
     * @param  mixed  $corpo
     */
    private function eGiaCatturato($corpo): bool
    {
        $testo = is_array($corpo) ? json_encode($corpo) : (string) $corpo;

        return is_string($testo) && str_contains($testo, 'ORDER_ALREADY_CAPTURED');
    }

    /**
     * Rilegge un ordine dal gateway. Null se la lettura non riesce.
     *
     * @return array<string, mixed>|null
     */
    private function leggiOrdineRemoto(string $paypalOrderId): ?array
    {
        $response = $this->client()->get("/v2/checkout/orders/{$paypalOrderId}");

        if ($response->failed()) {
            Log::error('PayPal: rilettura ordine fallita', [
                'paypal_order_id' => $paypalOrderId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return (array) $response->json();
    }

    /**
     * Incasso al ritorno del cliente dal gateway.
     *
     * La cattura viveva solo dentro il webhook: se quello non arriva — webhook
     * non registrato, dominio cambiato, firma che non verifica — il cliente ha
     * approvato il pagamento su PayPal e l'incasso non parte mai, con l'ordine
     * che resta in attesa fino all'annullamento automatico. Al ritorno dal
     * gateway PayPal rimanda l'identificativo dell'ordine nel parametro
     * `token`: si cattura subito, e il webhook (che di norma arriva lo stesso)
     * trova il lavoro già fatto e si ferma sull'idempotenza.
     *
     * @return array{payment_id: string, status: string, capture_status: string|null, order_id: int, amount: float|null}|null
     *                                                                                                                        null se l'ordine remoto non è pagabile o non corrisponde a quello locale
     */
    public function catturaAlRitorno(Order $order, string $paypalOrderId): ?array
    {
        $ordineRemoto = $this->leggiOrdineRemoto($paypalOrderId);

        if ($ordineRemoto === null) {
            return null;
        }

        // Il `token` arriva dalla barra degli indirizzi: senza questo controllo
        // chiunque potrebbe far registrare su un ordine il pagamento di un
        // altro.
        if (! $this->ordineRemotoCorrisponde($ordineRemoto, $order)) {
            Log::warning('PayPal: il token del ritorno non corrisponde all\'ordine', [
                'order_id' => $order->id,
                'paypal_order_id' => $paypalOrderId,
            ]);

            return null;
        }

        $stato = $ordineRemoto['status'] ?? '';

        // Come nel webhook: un ordine locale non piu' in attesa (annullato
        // nel frattempo) non si cattura. Chi torna dal gateway trova l'esito
        // dell'annullamento, non un addebito da rimborsare.
        if ($stato === 'APPROVED' && ! $this->ordineAncoraDaIncassare($order)) {
            return null;
        }

        if ($stato === 'COMPLETED') {
            return $this->esitoDa($paypalOrderId, $ordineRemoto, $ordineRemoto);
        }

        if ($stato !== 'APPROVED') {
            // CREATED, PAYER_ACTION_REQUIRED, VOIDED: il cliente non ha (ancora)
            // approvato, non c'è niente da incassare.
            return null;
        }

        return $this->catturaEDescrivi($paypalOrderId, $ordineRemoto);
    }

    /**
     * @param  array<string, mixed>  $ordineRemoto
     */
    private function ordineRemotoCorrisponde(array $ordineRemoto, Order $order): bool
    {
        $unita = $ordineRemoto['purchase_units'][0] ?? [];

        $custom = $unita['custom_id'] ?? ($unita['payments']['captures'][0]['custom_id'] ?? null);

        if ($custom !== null) {
            return (string) $custom === (string) $order->id;
        }

        return isset($unita['reference_id']) && (string) $unita['reference_id'] === (string) $order->order_number;
    }

    /**
     * PAYMENT.CAPTURE.COMPLETED: la cattura rimasta in sospeso e' andata a buon
     * fine. Per una cattura gia' COMPLETED al primo colpo l'evento arriva lo
     * stesso e si ferma sull'idempotenza della coppia (ordine, cattura).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleCaptureCompleted(array $payload): array
    {
        $cattura = $payload['resource'] ?? [];

        if (($cattura['status'] ?? null) !== 'COMPLETED' || empty($cattura['id'])) {
            return ['status' => 'ignored'];
        }

        return [
            'payment_id' => (string) $cattura['id'],
            'status' => 'completed',
            'capture_status' => 'COMPLETED',
            'order_id' => $this->ordineDellaCattura($cattura),
            'amount' => isset($cattura['amount']['value']) ? (float) $cattura['amount']['value'] : null,
        ];
    }

    /**
     * PAYMENT.CAPTURE.DENIED: la cattura in sospeso e' stata rifiutata, il
     * denaro non arrivera'. L'ordine va trattato come un pagamento fallito.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function handleCaptureDenied(array $payload): array
    {
        $cattura = $payload['resource'] ?? [];

        if (empty($cattura['id'])) {
            return ['status' => 'ignored'];
        }

        return [
            'payment_id' => (string) $cattura['id'],
            'status' => 'denied',
            'order_id' => $this->ordineDellaCattura($cattura),
        ];
    }

    /**
     * L'ordine locale di una cattura: dal suo `custom_id` (copia di quello
     * dell'unita' d'acquisto) o, se manca, rileggendo l'ordine PayPal a cui la
     * cattura appartiene. Zero se non si trova.
     *
     * @param  array<string, mixed>  $cattura
     */
    private function ordineDellaCattura(array $cattura): int
    {
        if (is_numeric($cattura['custom_id'] ?? null)) {
            return (int) $cattura['custom_id'];
        }

        $paypalOrderId = $cattura['supplementary_data']['related_ids']['order_id'] ?? null;
        $ordine = is_string($paypalOrderId) ? $this->leggiOrdineRemoto($paypalOrderId) : null;

        return $ordine !== null ? $this->ordineLocaleDellEvento($ordine)->id ?? 0 : 0;
    }

    /**
     * Handle PAYMENT.CAPTURE.REFUNDED event.
     */
    private function handleCaptureRefunded(array $payload): array
    {
        $resource = $payload['resource'] ?? [];

        // The refund resource 'id' is the refund ID, NOT the capture ID.
        // The order's payment_id stores the capture ID, so we must extract
        // the capture ID from the 'up' HATEOAS link (parent capture).
        $captureId = '';
        $captureLink = collect($resource['links'] ?? [])
            ->firstWhere('rel', 'up');

        if ($captureLink && ! empty($captureLink['href'])) {
            $captureId = basename(parse_url($captureLink['href'], PHP_URL_PATH));
        }

        // Fallback: se non troviamo il link, proviamo custom_id dalle supplementary_data
        if (empty($captureId)) {
            $captureId = $resource['custom_id'] ?? $resource['id'] ?? '';
        }

        // Rimborsato cumulato sulla cattura, se l'evento lo porta.
        $cumulato = $resource['seller_payable_breakdown']['total_refunded_amount']['value'] ?? null;

        return [
            'payment_id' => $captureId,
            'status' => 'refunded',
            'totale' => $this->catturaRimborsataDelTutto($captureId),
            'refunded_amount' => is_numeric($cumulato) ? (float) $cumulato : null,
        ];
    }

    /**
     * L'evento di rimborso arriva anche per i rimborsi parziali: a dire se la
     * cattura e' stata restituita tutta e' il suo stato, REFUNDED oppure
     * PARTIALLY_REFUNDED. Null se la cattura non si rilegge: decide allora il
     * cumulato confrontato col totale dell'ordine.
     */
    private function catturaRimborsataDelTutto(string $captureId): ?bool
    {
        if ($captureId === '') {
            return null;
        }

        $response = $this->client()->get("/v2/payments/captures/{$captureId}");

        if ($response->failed()) {
            Log::warning('PayPal: rilettura della cattura rimborsata non riuscita', [
                'capture_id' => $captureId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return match ($response->json('status')) {
            'REFUNDED' => true,
            'PARTIALLY_REFUNDED', 'COMPLETED' => false,
            default => null,
        };
    }
}

<?php

namespace App\Services\Payments;

use App\Models\Order;
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
        return Cache::remember('paypal_access_token', now()->addHours(8), function () {
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
                throw new RuntimeException('Impossibile ottenere il token PayPal');
            }

            return $response->json('access_token');
        });
    }

    /**
     * Make an authenticated HTTP client instance.
     */
    private function client(): \Illuminate\Http\Client\PendingRequest
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
                'return_url' => route('shop.checkout.success', ['order' => $order->order_token]),
                'cancel_url' => route('shop.checkout.cancel', ['order' => $order->order_token]),
            ],
        ]);

        if ($response->failed()) {
            Log::error('PayPal create order failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new RuntimeException('Impossibile creare l\'ordine PayPal');
        }

        $data = $response->json();

        // Find the HATEOAS 'approve' link
        $approveLink = collect($data['links'] ?? [])
            ->firstWhere('rel', 'approve');

        if (! $approveLink) {
            throw new RuntimeException('PayPal approve link non trovato nella risposta');
        }

        return $approveLink['href'];
    }

    /**
     * Verify PayPal webhook signature and process the event.
     */
    public function handleWebhook(string $rawBody, array $headers): array
    {
        $payload = json_decode($rawBody, true);

        // Verify webhook signature
        $this->verifyWebhookSignature($payload, $headers);

        $eventType = $payload['event_type'] ?? '';

        return match ($eventType) {
            'CHECKOUT.ORDER.APPROVED' => $this->handleOrderApproved($payload),
            'PAYMENT.CAPTURE.REFUNDED' => $this->handleCaptureRefunded($payload),
            default => ['status' => 'ignored'],
        };
    }

    /**
     * Issue a full refund for a captured PayPal payment.
     */
    public function refund(Order $order): bool
    {
        $response = $this->client()->post("/v2/payments/captures/{$order->payment_id}/refund", [
            'note_to_payer' => 'Rimborso ordine Savino Del Bene Volley',
        ]);

        if ($response->failed()) {
            Log::error('PayPal refund failed', [
                'order_id' => $order->id,
                'capture_id' => $order->payment_id,
                'body' => $response->json(),
            ]);
            throw new RuntimeException("Rimborso PayPal fallito per ordine {$order->order_number}");
        }

        Log::info('PayPal refund emesso', [
            'order_id' => $order->id,
            'capture_id' => $order->payment_id,
        ]);

        return true;
    }

    /**
     * Verify PayPal webhook signature via the PayPal API.
     *
     * @throws RuntimeException if verification fails
     */
    private function verifyWebhookSignature(array $payload, array $headers): void
    {
        $response = $this->client()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['PAYPAL-CERT-URL'] ?? $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => config('services.paypal.webhook_id'),
            'webhook_event' => $payload,
        ]);

        if ($response->failed()) {
            Log::warning('PayPal webhook signature verification request failed', [
                'status' => $response->status(),
            ]);
            throw new RuntimeException('Verifica firma webhook PayPal fallita');
        }

        $status = $response->json('verification_status');

        if ($status !== 'SUCCESS') {
            Log::warning('PayPal webhook signature non valida', [
                'verification_status' => $status,
            ]);
            throw new RuntimeException('Firma webhook PayPal non valida');
        }
    }

    /**
     * Handle CHECKOUT.ORDER.APPROVED: capture the payment.
     */
    private function handleOrderApproved(array $payload): array
    {
        $paypalOrderId = $payload['resource']['id'] ?? null;

        if (! $paypalOrderId) {
            throw new RuntimeException('PayPal order ID non trovato nel payload webhook');
        }

        // Capture the order
        $response = $this->client()->post("/v2/checkout/orders/{$paypalOrderId}/capture");

        if ($response->failed()) {
            Log::error('PayPal capture failed', [
                'paypal_order_id' => $paypalOrderId,
                'body' => $response->json(),
            ]);
            throw new RuntimeException('Cattura pagamento PayPal fallita');
        }

        $captureData = $response->json();
        $purchaseUnit = $captureData['purchase_units'][0] ?? [];
        $capture = $purchaseUnit['payments']['captures'][0] ?? [];
        $customId = $purchaseUnit['custom_id'] ?? null;

        return [
            'payment_id' => $capture['id'] ?? $paypalOrderId,
            'status' => 'completed',
            'order_id' => (int) $customId,
        ];
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

        return [
            'payment_id' => $captureId,
            'status' => 'refunded',
        ];
    }
}

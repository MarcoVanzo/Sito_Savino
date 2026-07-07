<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripePaymentService implements PaymentGatewayInterface
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe Checkout Session and return the redirect URL.
     */
    public function createSession(Order $order): string
    {
        $session = Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
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
            'customer_email' => $order->user?->email ?? $order->guest_email,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
            'success_url' => route('shop.checkout.success', ['order' => $order->order_token]).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('shop.checkout.cancel', ['order' => $order->order_token]),
        ]);

        return $session->url;
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
            'charge.refunded' => $this->handleChargeRefunded($event),
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

        Refund::create($payload);

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
        $session = $event->data->object;

        // Setup session (verifica metodo di pagamento per aste)
        if ($session->mode === 'setup') {
            return [
                'status' => 'setup_completed',
                'user_id' => (int) ($session->metadata->user_id ?? 0),
            ];
        }

        // Payment session (ordine e-commerce)
        return [
            'payment_id' => $session->payment_intent,
            'status' => 'completed',
            'order_id' => (int) $session->metadata->order_id,
        ];
    }

    /**
     * Handle charge.refunded event.
     */
    private function handleChargeRefunded(Event $event): array
    {
        $charge = $event->data->object;

        return [
            'payment_id' => $charge->payment_intent,
            'status' => 'refunded',
        ];
    }
}

<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Impalcatura per esercitare il webhook PayPal senza toccare la rete.
 *
 * Era copiata identica in due file di test. Averla in un posto solo non è
 * soltanto ordine: la sequenza di chiamate finte (token, verifica della firma,
 * capture) rispecchia il protocollo del gateway, e se cambia deve cambiare in
 * un punto — altrimenti una copia resta indietro e i suoi test continuano a
 * passare verificando un flusso che non esiste più.
 */
trait FakesPayPalWebhooks
{
    protected function configureFakePayPal(): void
    {
        config()->set('services.paypal.mode', 'sandbox');
        config()->set('services.paypal.client_id', 'test-id');
        config()->set('services.paypal.client_secret', 'test-secret');
        config()->set('services.paypal.webhook_id', 'test-webhook');
    }

    /**
     * @param  int  $orderId  ordine a cui il gateway attribuisce il pagamento
     * @param  string  $captureId  identificativo della transazione, per l'idempotenza
     */
    protected function fakePayPal(int $orderId, string $captureId = 'CAPTURE-1'): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/*/capture' => Http::response([
                'purchase_units' => [[
                    'custom_id' => (string) $orderId,
                    'payments' => ['captures' => [['id' => $captureId]]],
                ]],
            ]),
        ]);
    }

    protected function postWebhook(): TestResponse
    {
        return $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ]);
    }
}

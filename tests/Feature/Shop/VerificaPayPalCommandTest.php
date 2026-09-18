<?php

namespace Tests\Feature\Shop;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il comando che dice se PayPal è in grado di incassare.
 *
 * Dall'esterno non si distingue un impianto sano da uno rotto: il webhook
 * risponde 400 sia quando la firma non torna sia quando le credenziali sono
 * sbagliate. Questi casi bloccano le tre risposte che contano.
 */
class VerificaPayPalCommandTest extends TestCase
{
    private function configura(string $webhookId = 'WH-1'): void
    {
        config()->set('services.paypal.mode', 'sandbox');
        config()->set('services.paypal.client_id', 'id');
        config()->set('services.paypal.client_secret', 'secret');
        config()->set('services.paypal.webhook_id', $webhookId);
    }

    #[Test]
    public function senza_credenziali_il_comando_fallisce(): void
    {
        config()->set('services.paypal.client_id', '');
        config()->set('services.paypal.client_secret', '');

        $this->artisan('paypal:verifica')->assertFailed();
    }

    #[Test]
    public function un_webhook_che_non_esiste_e_un_fallimento(): void
    {
        $this->configura('WH-INESISTENTE');

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v1/notifications/webhooks' => Http::response(['webhooks' => [
                ['id' => 'WH-ALTRO', 'url' => 'https://altro.example/api/webhooks/paypal', 'event_types' => []],
            ]]),
        ]);

        $this->artisan('paypal:verifica')->assertFailed();
    }

    #[Test]
    public function un_webhook_su_un_altro_indirizzo_e_un_fallimento(): void
    {
        $this->configura();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v1/notifications/webhooks' => Http::response(['webhooks' => [[
                'id' => 'WH-1',
                'url' => 'https://vecchio-dominio.example/api/webhooks/paypal',
                'event_types' => [['name' => 'CHECKOUT.ORDER.APPROVED'], ['name' => 'PAYMENT.CAPTURE.REFUNDED']],
            ]]]),
        ]);

        $this->artisan('paypal:verifica')->assertFailed();
    }

    #[Test]
    public function impianto_a_posto(): void
    {
        $this->configura();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v1/notifications/webhooks' => Http::response(['webhooks' => [[
                'id' => 'WH-1',
                'url' => route('paypal.webhook'),
                'event_types' => [['name' => 'CHECKOUT.ORDER.APPROVED'], ['name' => 'PAYMENT.CAPTURE.REFUNDED']],
            ]]]),
        ]);

        $this->artisan('paypal:verifica')->assertSuccessful();
    }

    #[Test]
    public function un_webhook_senza_gli_eventi_giusti_e_un_fallimento(): void
    {
        $this->configura();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/v1/notifications/webhooks' => Http::response(['webhooks' => [[
                'id' => 'WH-1',
                'url' => route('paypal.webhook'),
                'event_types' => [['name' => 'PAYMENT.CAPTURE.COMPLETED']],
            ]]]),
        ]);

        $this->artisan('paypal:verifica')->assertFailed();
    }
}

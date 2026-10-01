<?php

namespace Tests\Feature\Webhooks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_webhook_rejects_without_signature(): void
    {
        $response = $this->postJson('/api/webhooks/stripe', [
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_fake']],
        ]);

        // StripeWebhookController returns 400 when signature is missing/invalid
        $response->assertStatus(400);
    }

    public function test_paypal_webhook_rejects_without_verification(): void
    {
        $response = $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAY-TEST-FAKE'],
        ]);

        // Should not return 200 (success) without proper verification
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_paypal_webhook_con_corpo_non_json_risponde_400_senza_errori(): void
    {
        Http::fake();
        Log::spy();

        $response = $this->call('POST', '/api/webhooks/paypal', [], [], [], ['CONTENT_TYPE' => 'application/json'], '');

        $response->assertStatus(400);
        Http::assertNothingSent();
        Log::shouldNotHaveReceived('error');
    }

    public function test_webhook_endpoints_exempt_from_csrf(): void
    {
        // POST without CSRF token — should NOT get 419 (CSRF mismatch)
        $response = $this->post('/api/webhooks/stripe', [
            'type' => 'test.event',
        ]);

        $this->assertNotEquals(419, $response->getStatusCode());
    }
}

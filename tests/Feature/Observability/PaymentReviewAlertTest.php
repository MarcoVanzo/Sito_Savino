<?php

namespace Tests\Feature\Observability;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un secondo incasso sullo stesso ordine è denaro da rimborsare.
 *
 * Finora il caso veniva registrato in `shop_events` e annotato sull'ordine, e
 * lì si fermava: nessuno veniva avvisato, quindi il rimborso partiva solo se
 * qualcuno andava a cercare quella riga. Questo test blocca il ritorno al
 * silenzio.
 */
class PaymentReviewAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config()->set('services.paypal.mode', 'sandbox');
        config()->set('services.paypal.client_id', 'test-id');
        config()->set('services.paypal.client_secret', 'test-secret');
        config()->set('services.paypal.webhook_id', 'test-webhook');
    }

    private function fakePayPal(int $orderId, string $captureId): void
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

    private function postWebhook(): TestResponse
    {
        return $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ]);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role->value])->save();

        return $user;
    }

    #[Test]
    public function un_doppio_incasso_avvisa_chi_puo_rimborsare(): void
    {
        $superAdmin = $this->userWithRole(UserRole::SuperAdmin);
        $shopManager = $this->userWithRole(UserRole::ShopManager);

        $order = Order::factory()->create();
        $order->forceFill([
            'status' => OrderStatus::Paid,
            'payment_id' => 'CAPTURE-OLD',
            'paid_at' => now(),
        ])->save();

        $this->fakePayPal($order->id, 'CAPTURE-NEW');
        $this->postWebhook()->assertOk();

        // Si conta solo questa notifica: la creazione dell'ordine ne genera già
        // altre (nuovo ordine, pagamento ricevuto) e un conteggio totale
        // passerebbe anche se l'avviso di revisione non partisse affatto.
        $title = "Ordine #{$order->order_number} da verificare";

        // Va a entrambi i ruoli: è materia di shop e di amministrazione.
        $this->assertSame(1, $superAdmin->notifications()->whereJsonContains('data->title', $title)->count());
        $this->assertSame(1, $shopManager->notifications()->whereJsonContains('data->title', $title)->count());
    }

    #[Test]
    public function un_pagamento_regolare_non_genera_avvisi(): void
    {
        // Il canale deve restare credibile: se si accendesse anche sugli
        // incassi normali, il primo doppio pagamento vero passerebbe inosservato.
        $superAdmin = $this->userWithRole(UserRole::SuperAdmin);

        $order = Order::factory()->create();
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        $this->fakePayPal($order->id, 'CAPTURE-1');
        $this->postWebhook()->assertOk();

        $this->assertSame(
            0,
            $superAdmin->notifications()
                ->whereJsonContains('data->title', "Ordine #{$order->order_number} da verificare")
                ->count(),
        );
    }
}

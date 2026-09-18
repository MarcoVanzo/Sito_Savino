<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Models\Order;
use App\Services\Payments\PayPalPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPayPalWebhooks;
use Tests\TestCase;

/**
 * L'incasso PayPal, dalle due parti da cui può arrivare.
 *
 * Fino a ieri la cattura viveva solo dentro il webhook e leggeva il numero
 * d'ordine da un campo che la risposta di PayPal non è tenuta a riportare:
 * bastava che mancasse perché il pagamento finisse su "ordine 0", cioè
 * incassato e mai registrato.
 */
class PayPalIncassoTest extends TestCase
{
    use FakesPayPalWebhooks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->configureFakePayPal();
    }

    private function ordinePayPal(): Order
    {
        $order = Order::factory()->create();

        $order->forceFill([
            'status' => OrderStatus::Pending,
            'payment_gateway' => PaymentGateway::PayPal,
            'payment_id' => null,
            'paid_at' => null,
            'total_price' => 49.90,
        ])->save();

        return $order->refresh();
    }

    #[Test]
    public function la_sessione_di_pagamento_nasce_con_gli_indirizzi_di_ritorno_del_sito(): void
    {
        $order = $this->ordinePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDER-1'],
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1'],
                ],
            ]),
        ]);

        $url = app(PayPalPaymentService::class)->createSession($order);

        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1', $url);

        Http::assertSent(function ($request) use ($order) {
            if (! str_ends_with($request->url(), '/v2/checkout/orders')) {
                return false;
            }

            $contesto = $request->data()['application_context'];

            return $contesto['return_url'] === $order->successUrl()
                && $contesto['cancel_url'] === $order->cancelUrl()
                && str_contains($contesto['return_url'], $order->order_token);
        });
    }

    #[Test]
    public function il_webhook_registra_il_pagamento_anche_se_la_cattura_non_riporta_il_custom_id(): void
    {
        $order = $this->ordinePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            // Risposta di cattura come la manda PayPal: reference_id, shipping,
            // payments. Nessun custom_id, da nessuna parte.
            '*/v2/checkout/orders/*/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'payments' => ['captures' => [['id' => 'CAPTURE-XYZ', 'status' => 'COMPLETED']]],
                ]],
            ]),
        ]);

        $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'PAYPAL-ORDER-1',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                ]],
            ],
        ])->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAPTURE-XYZ', $order->payment_id);
    }

    #[Test]
    public function senza_custom_id_da_nessuna_parte_l_ordine_si_ritrova_dal_numero(): void
    {
        $order = $this->ordinePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/*/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'payments' => ['captures' => [['id' => 'CAPTURE-SENZA-CUSTOM']]],
                ]],
            ]),
        ]);

        $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ])->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAPTURE-SENZA-CUSTOM', $order->payment_id);
    }

    #[Test]
    public function un_ordine_gia_catturato_non_manda_in_errore_il_webhook(): void
    {
        $order = $this->ordinePayPal();
        $order->forceFill(['payment_id' => 'CAPTURE-XYZ', 'paid_at' => now(), 'status' => OrderStatus::Paid])->save();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/*/capture' => Http::response([
                'name' => 'UNPROCESSABLE_ENTITY',
                'details' => [['issue' => 'ORDER_ALREADY_CAPTURED', 'description' => 'Order already captured.']],
            ], 422),
            '*/v2/checkout/orders/PAYPAL-ORDER-1' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'payments' => ['captures' => [['id' => 'CAPTURE-XYZ', 'status' => 'COMPLETED']]],
                ]],
            ]),
        ]);

        $risposta = $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ]);

        // 200 e non 400: un 400 fa ritentare PayPal per giorni su un pagamento
        // che è già a posto.
        $risposta->assertOk();
        $this->assertSame('CAPTURE-XYZ', $order->refresh()->payment_id);
    }

    #[Test]
    public function il_ritorno_dal_gateway_incassa_senza_aspettare_il_webhook(): void
    {
        $order = $this->ordinePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v2/checkout/orders/PAYPAL-ORDER-1/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'payments' => ['captures' => [['id' => 'CAPTURE-RITORNO', 'status' => 'COMPLETED']]],
                ]],
            ]),
            '*/v2/checkout/orders/PAYPAL-ORDER-1' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'APPROVED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                ]],
            ]),
        ]);

        $this->get(route('shop.checkout.success', ['orderToken' => $order->order_token]).'?token=PAYPAL-ORDER-1&PayerID=PAYER1')
            ->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAPTURE-RITORNO', $order->payment_id);
        $this->assertNotNull($order->paid_at);
    }

    #[Test]
    public function il_ritorno_con_il_token_di_un_altro_ordine_non_incassa_niente(): void
    {
        $order = $this->ordinePayPal();
        $altro = $this->ordinePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v2/checkout/orders/PAYPAL-ORDER-ALTRUI' => Http::response([
                'id' => 'PAYPAL-ORDER-ALTRUI',
                'status' => 'APPROVED',
                'purchase_units' => [[
                    'reference_id' => $altro->order_number,
                    'custom_id' => (string) $altro->id,
                ]],
            ]),
        ]);

        $this->get(route('shop.checkout.success', ['orderToken' => $order->order_token]).'?token=PAYPAL-ORDER-ALTRUI')
            ->assertOk();

        $this->assertNull($order->refresh()->payment_id);
        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/capture'));
    }

    #[Test]
    public function il_ritorno_non_tocca_un_ordine_gia_pagato(): void
    {
        $order = $this->ordinePayPal();
        $order->forceFill(['status' => OrderStatus::Paid, 'payment_id' => 'CAPTURE-GIA-FATTA', 'paid_at' => now()])->save();

        Http::fake();

        $this->get(route('shop.checkout.success', ['orderToken' => $order->order_token]).'?token=PAYPAL-ORDER-1')
            ->assertOk();

        Http::assertNothingSent();
        $this->assertSame('CAPTURE-GIA-FATTA', $order->refresh()->payment_id);
    }

    #[Test]
    public function un_incasso_piu_basso_del_totale_non_conferma_l_ordine(): void
    {
        $order = $this->ordinePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            // Il cliente ha pagato la sessione aperta su un carrello piu' magro.
            '*/v2/checkout/orders/*/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-PARZIALE',
                        'amount' => ['currency_code' => 'EUR', 'value' => '19.90'],
                    ]]],
                ]],
            ]),
        ]);

        $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ])->assertOk();

        $order->refresh();

        // Il pagamento e' un fatto e si registra; l'ordine no, aspetta una persona.
        $this->assertSame('CAPTURE-PARZIALE', $order->payment_id);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertDatabaseHas('shop_events', [
            'event_type' => 'payment_review',
            'viewable_id' => $order->id,
        ]);
    }

    #[Test]
    public function un_incasso_piu_alto_conferma_l_ordine_ma_lascia_il_segno(): void
    {
        $order = $this->ordinePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/*/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-ABBONDANTE',
                        'amount' => ['currency_code' => 'EUR', 'value' => '99.00'],
                    ]]],
                ]],
            ]),
        ]);

        $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ])->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertDatabaseHas('shop_events', [
            'event_type' => 'payment_review',
            'viewable_id' => $order->id,
        ]);
    }

    #[Test]
    public function l_importo_giusto_non_fa_scattare_nessuna_revisione(): void
    {
        $order = $this->ordinePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/*/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-ESATTA',
                        'amount' => ['currency_code' => 'EUR', 'value' => '49.90'],
                    ]]],
                ]],
            ]),
        ]);

        $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ])->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
        $this->assertDatabaseMissing('shop_events', [
            'event_type' => 'payment_review',
            'viewable_id' => $order->id,
        ]);
    }

    #[Test]
    public function il_ritorno_non_richiama_il_gateway_a_ogni_ricarica(): void
    {
        $order = $this->ordinePayPal();

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            // Ordine non ancora approvato: non c'e' niente da incassare e la
            // pagina continua a ricaricarsi.
            '*/v2/checkout/orders/PAYPAL-ORDER-1' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'PAYER_ACTION_REQUIRED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                ]],
            ]),
        ]);

        $indirizzo = route('shop.checkout.success', ['orderToken' => $order->order_token]).'?token=PAYPAL-ORDER-1';

        $this->get($indirizzo)->assertOk();
        $this->get($indirizzo)->assertOk();
        $this->get($indirizzo)->assertOk();

        // Una sola lettura dell'ordine remoto (piu' il token): le altre due
        // ricariche non hanno toccato il gateway.
        Http::assertSentCount(2);
    }

    #[Test]
    public function un_ordine_con_pagamento_registrato_non_viene_annullato_dal_comando(): void
    {
        $order = $this->ordinePayPal();

        // Come lo lascia un incasso di importo diverso dal totale: pagato, ma
        // in attesa che una persona decida.
        $order->forceFill([
            'payment_id' => 'CAPTURE-PARZIALE',
            'paid_at' => now(),
            'created_at' => now()->subHours(3),
        ])->save();

        $this->artisan('order:check-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    #[Test]
    public function un_ordine_con_pagamento_registrato_non_si_puo_ripagare(): void
    {
        $order = $this->ordinePayPal();
        $order->forceFill(['payment_id' => 'CAPTURE-PARZIALE', 'paid_at' => now()])->save();

        Http::fake();

        $this->actingAs($order->user)
            ->post(route('shop.checkout.retry', ['orderToken' => $order->order_token]))
            ->assertRedirect(route('shop.checkout.success', ['orderToken' => $order->order_token]));

        // Nessuna seconda sessione di pagamento aperta sul gateway.
        Http::assertNothingSent();
    }
}

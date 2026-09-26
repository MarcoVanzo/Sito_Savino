<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\StockMovementType;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesPayPalWebhooks;
use Tests\TestCase;

/**
 * Una cattura PayPal non conclusa non e' un incasso, e un ordine non piu' in
 * attesa non si cattura.
 *
 * La cattura PENDING veniva registrata come pagata: conferma d'ordine al
 * cliente e merce in partenza, con il denaro che poteva non arrivare mai.
 * E un ordine gia' annullato (merce tornata a scaffale) veniva catturato
 * lo stesso, lasciando un incasso da rimborsare a mano.
 */
class PayPalCatturaInSospesoTest extends TestCase
{
    use FakesPayPalWebhooks, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->configureFakePayPal();
    }

    /** @return array{Order, Product} */
    private function ordineInAttesa(): array
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 2, 'price_at_time_of_purchase' => 20,
        ]);
        StockMovement::create([
            'product_id' => $product->id, 'order_id' => $order->id,
            'quantity' => -2, 'type' => StockMovementType::Sale, 'notes' => 'checkout',
        ]);
        $order->forceFill([
            'status' => OrderStatus::Pending,
            'payment_gateway' => PaymentGateway::PayPal,
            'total_price' => 40.00,
        ])->save();

        return [$order->refresh(), $product];
    }

    private function fakeCattura(Order $order, string $stato, string $captureId = 'CAPTURE-P'): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/PAYPAL-ORDER-1/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'payments' => ['captures' => [[
                        'id' => $captureId,
                        'status' => $stato,
                        'amount' => ['value' => '40.00', 'currency_code' => 'EUR'],
                    ]]],
                ]],
            ]),
            '*/v2/checkout/orders/PAYPAL-ORDER-1' => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'APPROVED',
                'purchase_units' => [['reference_id' => $order->order_number, 'custom_id' => (string) $order->id]],
            ]),
        ]);
    }

    /** @param  array<string, mixed>  $resource */
    private function evento(string $tipo, array $resource): TestResponse
    {
        return $this->postJson('/api/webhooks/paypal', ['event_type' => $tipo, 'resource' => $resource]);
    }

    private function approvato(Order $order): TestResponse
    {
        return $this->evento('CHECKOUT.ORDER.APPROVED', [
            'id' => 'PAYPAL-ORDER-1',
            'purchase_units' => [['reference_id' => $order->order_number, 'custom_id' => (string) $order->id]],
        ]);
    }

    #[Test]
    public function una_cattura_pending_dal_webhook_non_conferma_l_ordine(): void
    {
        [$order] = $this->ordineInAttesa();
        $this->fakeCattura($order, 'PENDING');

        $this->approvato($order)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->payment_id);
        $this->assertNull($order->paid_at);
        $this->assertStringContainsString('CAPTURE-P', (string) $order->notes);
        Mail::assertNotQueued(OrderConfirmation::class);
    }

    #[Test]
    public function una_cattura_pending_al_ritorno_non_conferma_l_ordine(): void
    {
        [$order] = $this->ordineInAttesa();
        $this->fakeCattura($order, 'PENDING');

        $this->get(route('shop.checkout.success', ['orderToken' => $order->order_token]).'?token=PAYPAL-ORDER-1')
            ->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->payment_id);
        Mail::assertNotQueued(OrderConfirmation::class);
    }

    #[Test]
    public function la_cattura_completata_dopo_conferma_l_ordine_una_volta_sola(): void
    {
        [$order] = $this->ordineInAttesa();
        $this->fakeCattura($order, 'PENDING');
        $this->approvato($order)->assertOk();

        $completata = [
            'id' => 'CAPTURE-P',
            'status' => 'COMPLETED',
            'custom_id' => (string) $order->id,
            'amount' => ['value' => '40.00', 'currency_code' => 'EUR'],
        ];

        $this->evento('PAYMENT.CAPTURE.COMPLETED', $completata)->assertOk();
        $this->evento('PAYMENT.CAPTURE.COMPLETED', $completata)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('CAPTURE-P', $order->payment_id);
        Mail::assertQueued(OrderConfirmation::class, 1);
    }

    #[Test]
    public function la_cattura_completata_senza_custom_id_ritrova_l_ordine_da_paypal(): void
    {
        [$order] = $this->ordineInAttesa();
        $this->fakeCattura($order, 'PENDING');

        $this->evento('PAYMENT.CAPTURE.COMPLETED', [
            'id' => 'CAPTURE-P',
            'status' => 'COMPLETED',
            'amount' => ['value' => '40.00', 'currency_code' => 'EUR'],
            'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL-ORDER-1']],
        ])->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    #[Test]
    public function la_cattura_rifiutata_annulla_l_ordine_e_rimette_la_merce(): void
    {
        [$order, $product] = $this->ordineInAttesa();
        $this->fakeCattura($order, 'PENDING');
        $this->approvato($order)->assertOk();
        $this->assertSame(8, (int) $product->fresh()->stock);

        $rifiutata = ['id' => 'CAPTURE-P', 'status' => 'DECLINED', 'custom_id' => (string) $order->id];

        $this->evento('PAYMENT.CAPTURE.DENIED', $rifiutata)->assertOk();
        // Ripetuto: nessun secondo ripristino.
        $this->evento('PAYMENT.CAPTURE.DENIED', $rifiutata)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNull($order->payment_id);
        $this->assertSame(10, (int) $product->fresh()->stock);
        Mail::assertNotQueued(OrderConfirmation::class);
    }

    #[Test]
    public function la_cattura_rifiutata_non_tocca_un_ordine_pagato(): void
    {
        [$order] = $this->ordineInAttesa();
        $order->forceFill(['status' => OrderStatus::Paid, 'payment_id' => 'CAPTURE-ALTRA', 'paid_at' => now()])->save();
        $this->fakeCattura($order, 'PENDING');

        $this->evento('PAYMENT.CAPTURE.DENIED', ['id' => 'CAPTURE-P', 'custom_id' => (string) $order->id])->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    #[Test]
    public function un_ordine_gia_annullato_non_si_cattura_dal_webhook(): void
    {
        [$order] = $this->ordineInAttesa();
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();
        $this->fakeCattura($order, 'COMPLETED');

        $this->approvato($order)->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/capture'));
        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNull($order->payment_id);
    }

    #[Test]
    public function un_ordine_annullato_al_ritorno_non_si_cattura_e_mostra_l_annullamento(): void
    {
        [$order] = $this->ordineInAttesa();
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();
        $this->fakeCattura($order, 'COMPLETED');

        $this->get(route('shop.checkout.success', ['orderToken' => $order->order_token]).'?token=PAYPAL-ORDER-1')
            ->assertRedirect($order->cancelUrl());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/capture'));
        $this->assertNull($order->refresh()->payment_id);
    }
}

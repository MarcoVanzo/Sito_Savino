<?php

namespace Tests\Feature\Webhooks;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lo stock viene riservato al checkout (StockMovement di tipo Sale).
 * Il webhook di pagamento NON deve scaricarlo una seconda volta.
 */
class PaymentWebhookStockTest extends TestCase
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

    private function fakePayPal(int $orderId): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
            '*/v2/checkout/orders/*/capture' => Http::response([
                'purchase_units' => [
                    [
                        'custom_id' => (string) $orderId,
                        'payments' => ['captures' => [['id' => 'CAPTURE-1']]],
                    ],
                ],
            ]),
        ]);
    }

    public function test_payment_webhook_does_not_decrement_stock_again(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price_at_time_of_purchase' => 10,
        ]);

        // Riserva stock come fa CheckoutService al checkout
        StockMovement::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'quantity' => -3,
            'type' => StockMovementType::Sale,
            'notes' => 'Riservato al checkout',
        ]);

        $this->assertEquals(7, $product->fresh()->stock);

        $this->fakePayPal($order->id);

        $response = $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ]);

        $response->assertOk();

        // Lo stock resta quello riservato al checkout, non 4
        $this->assertEquals(7, $product->fresh()->stock);

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
        $this->assertEquals('CAPTURE-1', $order->payment_id);
    }
}

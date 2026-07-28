<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Mail\AuctionWon;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AuctionService;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ShopCorrectnessAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le scadenze delle aste sono confronti fra istanti: con l'orologio
        // reale una deadline fissata "un minuto fa" dipende da quanto impiega
        // la suite ad arrivare all'asserzione. Congelato il tempo, il salto
        // oltre la scadenza si fa con travel().
        $this->freezeTime();

        Mail::fake();
        config()->set('services.paypal.mode', 'sandbox');
        config()->set('services.paypal.client_id', 'test-id');
        config()->set('services.paypal.client_secret', 'test-secret');
        config()->set('services.paypal.webhook_id', 'test-webhook');
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function fakePayPal(int $orderId, string $captureId = 'CAPTURE-1'): void
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

    public function test_alternating_bids_do_not_bounce_between_the_same_two_users(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $auction = Auction::factory()->ended()->create(['current_bid' => 130]);

        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $a->id, 'amount' => 100]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $b->id, 'amount' => 110]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $a->id, 'amount' => 120]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $b->id, 'amount' => 130]);

        $auction->forceFill([
            'winner_user_id' => $b->id,
            'winner_checkout_token' => Str::uuid()->toString(),
            'winner_checkout_deadline' => now()->addHours(48),
            'current_winner_attempt' => 1,
        ])->save();

        $service = app(AuctionService::class);

        // B non paga entro la finestra → tocca ad A (non di nuovo a B)
        $this->travel(49)->hours();
        $service->checkWinnerPayments();
        $auction->refresh();
        $this->assertSame($a->id, $auction->winner_user_id);
        $this->assertEquals(120.0, $service->winningAmountFor($auction));

        // Nemmeno A paga entro la sua finestra → nessun altro offerente,
        // non si torna a B.
        $this->travel(49)->hours();
        $service->checkWinnerPayments();
        $auction->refresh();
        $this->assertNull($auction->winner_user_id);
    }

    public function test_auction_won_email_shows_the_amount_of_the_current_winner(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $auction = Auction::factory()->ended()->create(['current_bid' => 200]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $first->id, 'amount' => 200]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $second->id, 'amount' => 150]);

        $auction->forceFill([
            'winner_user_id' => $first->id,
            'winner_checkout_token' => Str::uuid()->toString(),
            'winner_checkout_deadline' => now()->addHours(48),
            'current_winner_attempt' => 1,
        ])->save();

        // Scaduta la finestra di pagamento del primo vincitore.
        $this->travel(49)->hours();

        app(AuctionService::class)->checkWinnerPayments();

        Mail::assertQueued(AuctionWon::class, function (AuctionWon $mail) use ($second) {
            $rendered = $mail->render();

            return $mail->winner->is($second)
                && str_contains($rendered, '150,00')
                && ! str_contains($rendered, '200,00');
        });
    }

    public function test_late_payment_on_cancelled_order_rededucts_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 3, 'price_at_time_of_purchase' => 10,
        ]);
        StockMovement::create([
            'product_id' => $product->id, 'order_id' => $order->id,
            'quantity' => -3, 'type' => StockMovementType::Sale, 'notes' => 'checkout',
        ]);

        $order->forceFill(['status' => OrderStatus::Cancelled])->save(); // observer ripristina
        $this->assertEquals(10, $product->fresh()->stock);

        $this->fakePayPal($order->id);
        $this->postWebhook()->assertOk();

        $order->refresh();
        $this->assertEquals(OrderStatus::Paid, $order->status);
        $this->assertEquals(7, $product->fresh()->stock, 'Lo stock va riscaricato.');

        // Rimborso successivo: lo stock deve tornare disponibile
        $order->forceFill(['status' => OrderStatus::Refunded])->save();
        $this->assertEquals(7, $product->fresh()->stock, 'Observer bloccato dal guard alreadyRestored.');

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            '*/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);
        // il rimborso via webhook riconcilia
        $order->forceFill(['status' => OrderStatus::Paid])->save();
        $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => ['id' => 'REFUND-1', 'links' => [['rel' => 'up', 'href' => 'https://api/v2/payments/captures/CAPTURE-1']]],
        ])->assertOk();

        $this->assertEquals(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertEquals(10, $product->fresh()->stock, 'Dopo il rimborso lo stock torna disponibile.');
    }

    public function test_late_payment_without_stock_does_not_confirm_the_order(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 3, 'price_at_time_of_purchase' => 10,
        ]);
        StockMovement::create([
            'product_id' => $product->id, 'order_id' => $order->id,
            'quantity' => -3, 'type' => StockMovementType::Sale, 'notes' => 'checkout',
        ]);
        $order->forceFill(['status' => OrderStatus::Cancelled])->save();

        // La merce è stata venduta ad altri nel frattempo
        $product->forceFill(['stock' => 1])->save();

        $this->fakePayPal($order->id);
        $this->postWebhook()->assertOk();

        $order->refresh();
        $this->assertEquals(OrderStatus::Cancelled, $order->status, 'Non deve passare a Paid.');
        $this->assertEquals('CAPTURE-1', $order->payment_id);
        $this->assertEquals(1, $product->fresh()->stock);
        $this->assertStringContainsString('REVISIONE MANUALE', (string) $order->notes);
        $this->assertDatabaseHas('shop_events', ['event_type' => 'payment_review', 'viewable_id' => $order->id]);
    }

    public function test_merge_on_login_uses_the_given_session_id_and_caps_to_max_qty(): void
    {
        SiteSetting::create(['key' => 'shop.max_qty_per_product', 'value' => '5', 'type' => 'integer', 'group' => 'shop']);
        Cache::flush();

        $product = Product::factory()->create(['stock' => 50]);
        $user = User::factory()->create();

        $guestCart = Cart::create(['session_id' => 'vecchia-sessione', 'expires_at' => now()->addDay()]);
        $guestCart->items()->create(['product_id' => $product->id, 'product_variant_id' => null, 'quantity' => 9]);

        Cart::create(['user_id' => $user->id, 'session_id' => 'altra', 'expires_at' => now()->addDay()]);

        app(CartService::class)->mergeOnLogin($user, 'vecchia-sessione');

        $userCart = Cart::where('user_id', $user->id)->first();
        $this->assertSame(5, (int) $userCart->items()->first()->quantity, 'Il merge deve rispettare shop.max_qty_per_product.');
        $this->assertDatabaseMissing('carts', ['session_id' => 'vecchia-sessione']);
    }

    public function test_second_payment_with_different_id_is_flagged(): void
    {
        $order = Order::factory()->create();
        $order->forceFill(['status' => OrderStatus::Paid, 'payment_id' => 'CAPTURE-OLD', 'paid_at' => now()])->save();

        $this->fakePayPal($order->id, 'CAPTURE-NEW');
        $this->postWebhook()->assertOk();

        $order->refresh();
        $this->assertEquals('CAPTURE-OLD', $order->payment_id);
        $this->assertStringContainsString('REVISIONE MANUALE', (string) $order->notes);
        $this->assertDatabaseHas('shop_events', ['event_type' => 'payment_review', 'viewable_id' => $order->id]);
    }
}

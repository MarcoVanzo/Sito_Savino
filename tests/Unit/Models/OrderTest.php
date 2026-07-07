<?php

namespace Tests\Unit\Models;

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $order->user);
        $this->assertEquals($user->id, $order->user->id);
    }

    public function test_order_has_many_items(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id]);

        $this->assertCount(1, $order->items);
        $this->assertInstanceOf(OrderItem::class, $order->items->first());
    }

    public function test_order_belongs_to_coupon(): void
    {
        $coupon = Coupon::factory()->create();
        $order = Order::factory()->create(['coupon_id' => $coupon->id]);

        $this->assertInstanceOf(Coupon::class, $order->coupon);
        $this->assertEquals($coupon->id, $order->coupon->id);
    }

    public function test_paid_scope(): void
    {
        $paidOrder = Order::factory()->create();
        $paidOrder->forceFill(['status' => OrderStatus::Paid])->save();
        $paidOrder->refresh();

        $pendingOrder = Order::factory()->create(); // Pending by default

        $results = Order::paid()->pluck('id')->toArray();

        $this->assertContains($paidOrder->id, $results);
        $this->assertNotContains($pendingOrder->id, $results);
    }

    public function test_for_guest_scope(): void
    {
        $guestOrder = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@test.com',
        ]);
        $otherOrder = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'other@test.com',
        ]);

        $results = Order::forGuest('guest@test.com')->pluck('id')->toArray();

        $this->assertContains($guestOrder->id, $results);
        $this->assertNotContains($otherOrder->id, $results);
    }

    public function test_is_guest_returns_true_when_no_user(): void
    {
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@test.com',
        ]);

        $this->assertTrue($order->isGuest());
    }

    public function test_is_guest_returns_false_when_user_exists(): void
    {
        $order = Order::factory()->create();

        $this->assertFalse($order->isGuest());
    }

    public function test_order_generates_order_number_on_create(): void
    {
        $order = Order::factory()->create();
        $order->refresh();

        $this->assertNotNull($order->order_number);
        $this->assertStringStartsWith('ORD-', $order->order_number);
    }

    public function test_order_generates_order_token_on_create(): void
    {
        $order = Order::factory()->create();
        $order->refresh();

        $this->assertNotNull($order->order_token);
    }

    public function test_order_uses_soft_deletes(): void
    {
        $order = Order::factory()->create();
        $orderId = $order->id;

        $order->delete();

        $this->assertSoftDeleted('orders', ['id' => $orderId]);
        $this->assertNotNull(Order::withTrashed()->find($orderId));
    }
}

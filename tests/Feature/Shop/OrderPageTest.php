<?php

namespace Tests\Feature\Shop;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function authenticated_user_can_view_orders_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('shop.orders'));
        $response->assertStatus(200);
    }

    #[Test]
    public function guest_cannot_view_orders_list(): void
    {
        $response = $this->get(route('shop.orders'));
        $response->assertRedirect();
    }

    #[Test]
    public function order_show_displays_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $order->refresh();

        $response = $this->actingAs($user)->get(route('shop.order.show', $order->order_number));
        $response->assertStatus(200);
    }
}

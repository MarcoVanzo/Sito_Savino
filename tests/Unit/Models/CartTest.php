<?php
namespace Tests\Unit\Models;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $cart->user);
        $this->assertEquals($user->id, $cart->user->id);
    }

    public function test_cart_has_many_items(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->create(['cart_id' => $cart->id]);

        $this->assertCount(1, $cart->items);
        $this->assertInstanceOf(CartItem::class, $cart->items->first());
    }

    public function test_expires_at_is_cast_to_datetime(): void
    {
        $cart = Cart::factory()->create(['expires_at' => now()->addHours(2)]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $cart->expires_at);
    }

    public function test_prunable_returns_expired_carts(): void
    {
        // Carrello scaduto da più di 7 giorni (dovrebbe essere pruned)
        $expiredCart = Cart::factory()->create([
            'expires_at' => now()->subDays(8),
        ]);

        // Carrello ancora valido (non dovrebbe essere pruned)
        $activeCart = Cart::factory()->create([
            'expires_at' => now()->addDay(),
        ]);

        $prunableIds = (new Cart)->prunable()->pluck('id')->toArray();

        $this->assertContains($expiredCart->id, $prunableIds);
        $this->assertNotContains($activeCart->id, $prunableIds);
    }
}

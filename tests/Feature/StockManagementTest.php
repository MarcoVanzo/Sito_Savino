<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderWithProduct(int $stock = 50, int $quantity = 2): array
    {
        $product = Product::factory()->create(['stock' => $stock]);
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price_at_time_of_purchase' => $product->price,
        ]);

        return [$order, $product];
    }

    public function test_stock_decrements_when_order_is_paid(): void
    {
        [$order, $product] = $this->createOrderWithProduct(stock: 50, quantity: 3);

        $order->update(['status' => OrderStatus::Paid]);

        // Lo StockMovementObserver decrementa lo stock atomicamente
        $product->refresh();
        $this->assertEquals(47, $product->stock);

        // Verifica che il movimento sia stato creato
        $this->assertDatabaseHas('stock_movements', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => -3,
            'type' => StockMovementType::Sale->value,
        ]);
    }

    public function test_stock_is_not_decremented_twice_for_same_order(): void
    {
        [$order, $product] = $this->createOrderWithProduct(stock: 50, quantity: 5);

        // Simula doppio pagamento (idempotenza)
        $order->update(['status' => OrderStatus::Paid]);

        // Forza un secondo trigger: reimposta e ri-aggiorna
        $order->status = OrderStatus::Pending;
        $order->saveQuietly(); // saveQuietly per non triggerare observer
        $order->update(['status' => OrderStatus::Paid]);

        $product->refresh();
        // Stock decrementato solo una volta
        $this->assertEquals(45, $product->stock);
        $this->assertEquals(1, StockMovement::where('order_id', $order->id)->where('type', StockMovementType::Sale)->count());
    }

    public function test_stock_restores_when_paid_order_is_cancelled(): void
    {
        [$order, $product] = $this->createOrderWithProduct(stock: 50, quantity: 4);

        $order->update(['status' => OrderStatus::Paid]);
        $product->refresh();
        $this->assertEquals(46, $product->stock);

        $order->update(['status' => OrderStatus::Cancelled]);
        $product->refresh();
        $this->assertEquals(50, $product->stock);

        // Verifica i movimenti
        $this->assertEquals(1, StockMovement::where('order_id', $order->id)->where('type', StockMovementType::Sale)->count());
        $this->assertEquals(1, StockMovement::where('order_id', $order->id)->where('type', StockMovementType::Adjustment)->count());
    }

    public function test_stock_restore_is_idempotent(): void
    {
        [$order, $product] = $this->createOrderWithProduct(stock: 50, quantity: 4);

        $order->update(['status' => OrderStatus::Paid]);
        $order->update(['status' => OrderStatus::Cancelled]);

        // Simula doppio tentativo di cancellazione
        $order->status = OrderStatus::Paid;
        $order->saveQuietly();
        $order->update(['status' => OrderStatus::Cancelled]);

        $product->refresh();
        // Stock ripristinato solo una volta
        $this->assertEquals(50, $product->stock);
    }

    public function test_pending_order_cancellation_does_not_restore_stock(): void
    {
        [$order, $product] = $this->createOrderWithProduct(stock: 50, quantity: 3);

        // Cancella un ordine MAI pagato
        $order->update(['status' => OrderStatus::Cancelled]);

        $product->refresh();
        $this->assertEquals(50, $product->stock);
        $this->assertEquals(0, StockMovement::where('order_id', $order->id)->count());
    }

    public function test_stock_movement_observer_prevents_negative_stock(): void
    {
        $product = Product::factory()->create(['stock' => 2]);

        // Crea un movimento che toglierebbe più stock di quello disponibile
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insufficiente');

        StockMovement::create([
            'product_id' => $product->id,
            'quantity' => -5,
            'type' => StockMovementType::Sale,
            'notes' => 'Test negative stock',
        ]);
    }
}

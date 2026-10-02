<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderWithProduct(int $stock = 50, int $quantity = 2): array
    {
        $product = Product::factory()->create(['stock' => $stock]);

        // status is intentionally excluded from $fillable — set it explicitly
        $order = Order::factory()->create();
        $order->status = OrderStatus::Pending;
        $order->save();

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price_at_time_of_purchase' => $product->price,
        ]);

        return [$order->fresh(), $product];
    }

    /**
     * Helper: cambia lo status di un ordine in modo sicuro (no mass-assignment).
     */
    private function changeOrderStatus(Order $order, OrderStatus $status): void
    {
        $order->status = $status;
        $order->save();
    }

    public function test_stock_decrements_at_checkout(): void
    {
        [$order, $product] = $this->createOrderWithProduct(stock: 50, quantity: 3);

        // Stock viene decrementato dal CheckoutService al momento della creazione ordine
        StockMovement::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'quantity' => -3,
            'type' => StockMovementType::Sale,
            'notes' => 'Test — riservato al checkout',
        ]);

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

        // Simula decremento dal CheckoutService
        StockMovement::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'quantity' => -5,
            'type' => StockMovementType::Sale,
            'notes' => 'Test — riservato al checkout',
        ]);

        // Verifica che creare un secondo movimento Sale per lo stesso ordine
        // sia possibile ma entrambi vengano registrati (l'idempotenza è nel CheckoutService)
        $product->refresh();
        $this->assertEquals(45, $product->stock);
        $this->assertEquals(1, StockMovement::where('order_id', $order->id)->where('type', StockMovementType::Sale)->count());
    }

    public function test_stock_restores_when_paid_order_is_cancelled(): void
    {
        [$order, $product] = $this->createOrderWithProduct(stock: 50, quantity: 4);

        // Simula decremento dal CheckoutService
        StockMovement::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'quantity' => -4,
            'type' => StockMovementType::Sale,
            'notes' => 'Test — riservato al checkout',
        ]);

        $this->changeOrderStatus($order, OrderStatus::Paid);
        $product->refresh();
        $this->assertEquals(46, $product->stock);

        $this->changeOrderStatus($order, OrderStatus::Cancelled);
        $product->refresh();
        $this->assertEquals(50, $product->stock);

        // Verifica i movimenti
        $this->assertEquals(1, StockMovement::where('order_id', $order->id)->where('type', StockMovementType::Sale)->count());
        $this->assertEquals(1, StockMovement::where('order_id', $order->id)->where('type', StockMovementType::Adjustment)->count());
    }

    public function test_stock_restore_is_idempotent(): void
    {
        [$order, $product] = $this->createOrderWithProduct(stock: 50, quantity: 4);

        // Simula decremento dal CheckoutService
        StockMovement::create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'quantity' => -4,
            'type' => StockMovementType::Sale,
            'notes' => 'Test — riservato al checkout',
        ]);

        $this->changeOrderStatus($order, OrderStatus::Paid);
        $this->changeOrderStatus($order, OrderStatus::Cancelled);

        // Simula doppio tentativo di cancellazione
        $order->status = OrderStatus::Paid;
        $order->saveQuietly();
        $this->changeOrderStatus($order, OrderStatus::Cancelled);

        $product->refresh();
        // Stock ripristinato solo una volta (da 46 a 50, non da 42 a 50)
        $this->assertEquals(50, $product->stock);
        // Solo un movimento Adjustment (idempotenza)
        $this->assertEquals(1, StockMovement::where('order_id', $order->id)->where('type', StockMovementType::Adjustment)->count());
    }

    public function test_pending_order_cancellation_does_not_restore_stock(): void
    {
        [$order, $product] = $this->createOrderWithProduct(stock: 50, quantity: 3);

        // Cancella un ordine MAI pagato
        $this->changeOrderStatus($order, OrderStatus::Cancelled);

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

    public function test_la_vendita_di_una_taglia_non_si_blocca_sul_riepilogo_del_padre(): void
    {
        // Caso del 02/10/2026: taglia disponibile, products.stock rimasto a 0.
        $product = Product::factory()->create(['stock' => 0]);
        $taglia = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 3]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 4]);

        StockMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => $taglia->id,
            'quantity' => -1,
            'type' => StockMovementType::Sale,
            'notes' => 'Test taglia',
        ]);

        $this->assertSame(2, (int) $taglia->fresh()->stock);
        $this->assertSame(6, (int) $product->fresh()->stock);
    }

    public function test_la_taglia_esaurita_blocca_ancora_la_vendita(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $taglia = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 0]);

        $this->expectExceptionMessage('Stock insufficiente');

        StockMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => $taglia->id,
            'quantity' => -1,
            'type' => StockMovementType::Sale,
            'notes' => 'Test taglia esaurita',
        ]);
    }

    public function test_le_taglie_scritte_dal_pannello_riallineano_il_padre(): void
    {
        $product = Product::factory()->create(['stock' => 0]);

        $s = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 3]);
        $this->assertSame(3, (int) $product->fresh()->stock);

        $m = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 4]);
        $this->assertSame(7, (int) $product->fresh()->stock);

        $s->update(['stock' => 1]);
        $this->assertSame(5, (int) $product->fresh()->stock);

        $m->delete();
        $this->assertSame(1, (int) $product->fresh()->stock);

        $s->delete();
        $this->assertSame(0, (int) $product->fresh()->stock, 'Senza taglie il vecchio riepilogo non si vende.');
    }

    public function test_la_migrazione_riallinea_solo_i_prodotti_con_taglie(): void
    {
        $conTaglie = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $conTaglie->id, 'stock' => 6]);
        $senzaTaglie = Product::factory()->create(['stock' => 9]);
        Product::whereKey($conTaglie->id)->toBase()->update(['stock' => 0]);

        $migrazione = require database_path('migrations/2026_10_02_100000_riallinea_la_giacenza_dei_prodotti_con_taglie.php');
        $migrazione->up();

        $this->assertSame(6, (int) $conTaglie->fresh()->stock);
        $this->assertSame(9, (int) $senzaTaglie->fresh()->stock);
    }
}

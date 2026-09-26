<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShippingZone;
use App\Models\User;
use App\Policies\OrderItemPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Soglia di spedizione, prezzo scontato e pannello degli ordini.
 *
 * - una soglia di spedizione gratuita a 0 ("0.00", stringa non vuota quindi
 *   vera per PHP) regalava la spedizione a ogni ordine;
 * - un "prezzo scontato" piu' alto del listino veniva applicato come sconto;
 * - le righe di un ordine si cambiavano dal pannello senza toccare il
 *   magazzino, e lo stato scelto nel modulo di modifica finiva nel nulla.
 */
class PrezziSoglieEPannelloOrdiniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function utente(UserRole $ruolo): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $ruolo, 'is_active' => true])->save();

        return $user->refresh();
    }

    #[Test]
    public function una_soglia_a_zero_non_regala_la_spedizione(): void
    {
        $zona = ShippingZone::factory()->create(['flat_rate' => 7.90, 'free_threshold' => 0, 'weight_rates' => null]);

        $this->assertSame(7.90, $zona->fresh()->calculateShippingCost(25.00));
    }

    #[Test]
    public function una_soglia_vera_regala_ancora_la_spedizione(): void
    {
        $zona = ShippingZone::factory()->create(['flat_rate' => 7.90, 'free_threshold' => 100, 'weight_rates' => null]);

        $this->assertSame(0.0, $zona->fresh()->calculateShippingCost(120.00));
        $this->assertSame(7.90, $zona->fresh()->calculateShippingCost(99.99));
    }

    #[Test]
    public function un_prezzo_scontato_non_inferiore_al_listino_non_e_uno_sconto(): void
    {
        $piuAlto = Product::factory()->create(['price' => 20, 'sale_price' => 25]);
        $uguale = Product::factory()->create(['price' => 20, 'sale_price' => 20]);
        $vero = Product::factory()->create(['price' => 20, 'sale_price' => 15]);

        $this->assertFalse($piuAlto->isOnSale());
        $this->assertSame(20.0, $piuAlto->effectivePrice());
        $this->assertFalse($uguale->isOnSale());
        $this->assertSame(20.0, $uguale->effectivePrice());
        $this->assertTrue($vero->isOnSale());
        $this->assertSame(15.0, $vero->effectivePrice());
    }

    #[Test]
    public function lo_sconto_in_blocco_salta_i_prodotti_che_costano_meno_dello_sconto(): void
    {
        $caro = Product::factory()->create(['price' => 40, 'sale_price' => null, 'product_category_id' => ProductCategory::factory()]);
        $economico = Product::factory()->create(['price' => 10, 'sale_price' => null, 'product_category_id' => ProductCategory::factory()]);
        $pari = Product::factory()->create(['price' => 25, 'sale_price' => null, 'product_category_id' => ProductCategory::factory()]);

        Livewire::actingAs($this->utente(UserRole::SuperAdmin))
            ->test(ListProducts::class)
            ->callTableBulkAction('apply_discount', [$caro, $economico, $pari], ['sale_price' => 25])
            ->assertNotified();

        $this->assertSame('25.00', (string) $caro->fresh()->sale_price);
        $this->assertNull($economico->fresh()->sale_price);
        $this->assertNull($pari->fresh()->sale_price);
    }

    #[Test]
    public function le_righe_di_un_ordine_non_in_attesa_non_si_toccano_neanche_dal_resp_shop(): void
    {
        $policy = new OrderItemPolicy;
        $shop = $this->utente(UserRole::ShopManager);

        foreach ([OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Cancelled, OrderStatus::Refunded] as $stato) {
            $order = Order::factory()->create();
            // Anche senza paid_at (bonifico annullato, ordine spedito a mano).
            $order->forceFill(['status' => $stato, 'paid_at' => null])->save();
            $riga = OrderItem::factory()->create(['order_id' => $order->id]);

            $this->assertFalse($policy->update($shop, $riga), "update su ordine {$stato->value}");
            $this->assertFalse($policy->delete($shop, $riga), "delete su ordine {$stato->value}");
        }
    }

    #[Test]
    public function le_righe_d_ordine_nel_pannello_sono_di_sola_lettura(): void
    {
        $order = Order::factory()->create();
        $order->forceFill(['status' => OrderStatus::Pending])->save();
        $riga = OrderItem::factory()->create(['order_id' => $order->id]);

        Livewire::actingAs($this->utente(UserRole::ShopManager))
            ->test(OrderItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditOrder::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$riga])
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('create');
    }

    #[Test]
    public function lo_stato_non_si_cambia_dal_modulo_di_modifica(): void
    {
        $order = Order::factory()->create();
        $order->forceFill(['status' => OrderStatus::Pending])->save();

        Livewire::actingAs($this->utente(UserRole::SuperAdmin))
            ->test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldIsDisabled('status');
    }
}

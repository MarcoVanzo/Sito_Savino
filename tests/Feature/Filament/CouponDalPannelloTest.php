<?php

namespace Tests\Feature\Filament;

use App\Enums\CouponType;
use App\Enums\UserRole;
use App\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Filament\Resources\CouponResource\Pages\EditCoupon;
use App\Filament\Resources\CouponResource\Pages\ListCoupons;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Codici promozionali: il legame con i prodotti si crea dal pannello.
 */
class CouponDalPannelloTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function il_coupon_si_lega_a_prodotti_e_categorie(): void
    {
        $maglia = Product::factory()->create(['product_category_id' => ProductCategory::factory()]);
        $categoria = ProductCategory::factory()->create();

        Livewire::actingAs($this->superAdmin())
            ->test(CreateCoupon::class)
            ->fillForm([
                'code' => 'compleanno-kate',
                'type' => CouponType::Percentage->value,
                'value' => 10,
                'products' => [$maglia->id],
                'categories' => [$categoria->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::firstOrFail();

        $this->assertTrue($coupon->haLimitiDiCatalogo());
        $this->assertTrue($coupon->valePerIlProdotto($maglia));
        $this->assertTrue($coupon->valePerIlProdotto(
            Product::factory()->create(['product_category_id' => $categoria->id]),
        ));
        $this->assertFalse($coupon->valePerIlProdotto(
            Product::factory()->create(['product_category_id' => ProductCategory::factory()]),
        ));
    }

    #[Test]
    public function un_coupon_senza_elenchi_resta_valido_su_tutto(): void
    {
        $coupon = Coupon::factory()->create();

        Livewire::actingAs($this->superAdmin())
            ->test(EditCoupon::class, ['record' => $coupon->getRouteKey()])
            ->assertFormSet(['products' => [], 'categories' => []]);

        $this->assertFalse($coupon->haLimitiDiCatalogo());
        $this->assertTrue($coupon->valePerIlProdotto(Product::factory()->create()));
    }

    #[Test]
    public function l_elenco_dice_se_il_coupon_e_limitato(): void
    {
        $limitato = Coupon::factory()->create(['code' => 'SOLO-MAGLIA']);
        $limitato->products()->attach(Product::factory()->create());

        Coupon::factory()->create(['code' => 'SALDI']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCoupons::class)
            ->assertSuccessful()
            ->assertSee('Solo alcuni prodotti')
            ->assertSee('Tutto il carrello');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user->refresh();
    }
}

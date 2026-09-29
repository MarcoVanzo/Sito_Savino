<?php

namespace Tests\Feature\Shop;

use App\Enums\CouponType;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Pages\MagazzinoPage;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Richieste della redazione del 29/09/2026:
 *
 * - l'ordine dei prodotti si sceglie dal pannello (le maglie gara per
 *   numero), e le categorie lo seguono invece di aprirsi sui piu' recenti;
 * - un prodotto puo' stare in piu' categorie (la maglia del libero in Home e
 *   in Away, un capo anche in Outlet).
 */
class OrdineECategorieDellaVetrinaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function amministratore(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        return $user->refresh();
    }

    private function maglia(ProductCategory $categoria, string $nome, int $posizione): Product
    {
        return Product::factory()->create([
            'name' => $nome,
            'product_category_id' => $categoria->id,
            'sort_order' => $posizione,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function nomiInCategoria(ProductCategory $categoria, array $query = []): array
    {
        $nomi = [];

        $this->get(route('shop.category', ['category' => $categoria->slug, ...$query]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$nomi) {
                $nomi = collect($page->toArray()['props']['products']['data'])->pluck('name')->all();
            });

        return $nomi;
    }

    #[Test]
    public function la_categoria_si_apre_nell_ordine_scelto_in_redazione(): void
    {
        $home = ProductCategory::factory()->create();
        $this->maglia($home, 'Maglia #9', 3);
        $this->maglia($home, 'Maglia #6', 1);
        $this->maglia($home, 'Maglia #7', 2);

        $this->assertSame(['Maglia #6', 'Maglia #7', 'Maglia #9'], $this->nomiInCategoria($home));
    }

    #[Test]
    public function il_riordino_del_pannello_arriva_alla_vetrina(): void
    {
        $home = ProductCategory::factory()->create();
        $sei = $this->maglia($home, 'Maglia #6', 1);
        $sette = $this->maglia($home, 'Maglia #7', 2);
        $nove = $this->maglia($home, 'Maglia #9', 3);

        // Riempie la cache della vetrina: il riordino deve buttarla.
        $this->get(route('shop'))->assertOk();

        Livewire::actingAs($this->amministratore())
            ->test(ListProducts::class)
            ->call('reorderTable', [$nove->id, $sei->id, $sette->id]);

        $this->assertSame(['Maglia #9', 'Maglia #6', 'Maglia #7'], $this->nomiInCategoria($home));

        $this->get(route('shop'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('allProducts.0.name', 'Maglia #9'));
    }

    #[Test]
    public function riordinare_una_categoria_non_sposta_le_altre(): void
    {
        $home = ProductCategory::factory()->create();
        $sciarpe = ProductCategory::factory()->create();

        $a = $this->maglia($home, 'Maglia A', 1);
        $sciarpa = $this->maglia($sciarpe, 'Sciarpa', 2);
        $b = $this->maglia($home, 'Maglia B', 3);

        Livewire::actingAs($this->amministratore())
            ->test(ListProducts::class)
            ->call('reorderTable', [$b->id, $a->id]);

        // Le due maglie si scambiano le posizioni 1 e 3; la sciarpa resta in mezzo.
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(3, $a->fresh()->sort_order);
        $this->assertSame(2, $sciarpa->fresh()->sort_order);
    }

    #[Test]
    public function si_puo_ancora_scegliere_di_vedere_i_piu_recenti(): void
    {
        $home = ProductCategory::factory()->create();
        $vecchia = $this->maglia($home, 'Vecchia', 1);
        $vecchia->forceFill(['created_at' => now()->subYear()])->save();
        $this->maglia($home, 'Nuova', 2);

        $this->assertSame(['Nuova', 'Vecchia'], $this->nomiInCategoria($home, ['sort' => 'newest']));
    }

    #[Test]
    public function un_prodotto_compare_in_tutte_le_sue_categorie(): void
    {
        $home = ProductCategory::factory()->create();
        $away = ProductCategory::factory()->create();
        $libero = $this->maglia($home, 'Maglia libero', 1);
        $libero->altreCategorie()->attach($away);

        $this->assertSame(['Maglia libero'], $this->nomiInCategoria($home));
        $this->assertSame(['Maglia libero'], $this->nomiInCategoria($away));
    }

    #[Test]
    public function la_vetrina_conta_e_filtra_anche_le_categorie_aggiuntive(): void
    {
        $abbigliamento = ProductCategory::factory()->create();
        $outlet = ProductCategory::factory()->create();
        $felpa = $this->maglia($abbigliamento, 'Felpa', 1);
        $felpa->altreCategorie()->attach($outlet);

        $this->get(route('shop'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('allProducts.0.category.id', $abbigliamento->id)
            ->where('allProducts.0.category_ids', [$abbigliamento->id, $outlet->id])
            ->where('categories', fn ($categorie) => collect($categorie)->firstWhere('id', $outlet->id)['products_count'] === 1));
    }

    #[Test]
    public function la_sottocategoria_conta_i_prodotti_aggiunti(): void
    {
        $kit = ProductCategory::factory()->create();
        $home = ProductCategory::factory()->create(['parent_id' => $kit->id]);
        $away = ProductCategory::factory()->create(['parent_id' => $kit->id]);
        $this->maglia($home, 'Maglia #6', 1);
        $libero = $this->maglia($home, 'Maglia libero', 2);
        $libero->altreCategorie()->attach($away);

        $this->get(route('shop.category', ['category' => $kit->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('subcategories', fn ($figlie) => collect($figlie)->pluck('products_count', 'id')->all() === [$home->id => 2, $away->id => 1])
                ->count('products.data', 2));

        $this->assertSame(['Maglia libero'], $this->nomiInCategoria($kit, ['gruppo' => $away->slug]));
    }

    #[Test]
    public function il_coupon_di_una_categoria_vale_anche_sui_prodotti_aggiunti(): void
    {
        $abbigliamento = ProductCategory::factory()->create();
        $outlet = ProductCategory::factory()->create();
        $felpa = $this->maglia($abbigliamento, 'Felpa', 1);
        $felpa->altreCategorie()->attach($outlet);

        $coupon = Coupon::factory()->create(['type' => CouponType::Percentage, 'value' => 10]);
        $coupon->categories()->attach($outlet);

        $this->assertTrue($coupon->valePerIlProdotto($felpa->fresh()));
        $this->assertFalse($coupon->valePerIlProdotto($this->maglia($abbigliamento, 'Cappello', 2)));
    }

    #[Test]
    public function il_filtro_del_pannello_trova_anche_le_categorie_aggiuntive(): void
    {
        $home = ProductCategory::factory()->create();
        $away = ProductCategory::factory()->create();
        $libero = $this->maglia($home, 'Maglia libero', 1);
        $libero->altreCategorie()->attach($away);
        $solo = $this->maglia($home, 'Maglia #6', 2);

        Livewire::actingAs($this->amministratore())
            ->test(ListProducts::class)
            ->filterTable('product_category_id', $away->id)
            ->assertCanSeeTableRecords([$libero])
            ->assertCanNotSeeTableRecords([$solo]);
    }

    #[Test]
    public function un_prodotto_nuovo_entra_in_cima_con_una_posizione_propria(): void
    {
        $home = ProductCategory::factory()->create();
        $sei = $this->maglia($home, 'Maglia #6', 1);
        $sette = $this->maglia($home, 'Maglia #7', 2);

        $nuova = Product::factory()->create(['name' => 'Maglia #1', 'product_category_id' => $home->id]);

        $this->assertSame(1, $nuova->fresh()->sort_order);
        $this->assertSame(2, $sei->fresh()->sort_order);
        $this->assertSame(3, $sette->fresh()->sort_order);
        $this->assertSame(['Maglia #1', 'Maglia #6', 'Maglia #7'], $this->nomiInCategoria($home));
    }

    #[Test]
    public function la_copia_di_un_prodotto_non_ne_divide_la_posizione(): void
    {
        $home = ProductCategory::factory()->create();
        $away = ProductCategory::factory()->create();
        $libero = $this->maglia($home, 'Maglia libero', 5);
        $libero->altreCategorie()->attach($away);

        Livewire::actingAs($this->amministratore())
            ->test(ListProducts::class)
            ->callTableAction('duplicate', $libero);

        $copia = Product::whereKeyNot($libero->id)->sole();

        $this->assertSame(1, $copia->sort_order);
        $this->assertSame(6, $libero->fresh()->sort_order);
        $this->assertSame([$home->id, $away->id], $copia->idCategorie());
    }

    #[Test]
    public function la_categoria_principale_non_si_ripete_fra_le_aggiuntive(): void
    {
        $home = ProductCategory::factory()->create();
        $away = ProductCategory::factory()->create();
        $libero = $this->maglia($home, 'Maglia libero', 1);
        $libero->altreCategorie()->attach($away);

        // Away diventa la principale: non deve restare anche fra le "Anche in".
        Livewire::actingAs($this->amministratore())
            ->test(EditProduct::class, ['record' => $libero->getRouteKey()])
            ->fillForm(['product_category_id' => $away->id, 'altreCategorie' => [$home->id, $away->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$home->id], $libero->fresh()->altreCategorie->pluck('id')->all());
    }

    #[Test]
    public function il_modulo_di_creazione_salva_le_categorie_aggiuntive(): void
    {
        $home = ProductCategory::factory()->create();
        $away = ProductCategory::factory()->create();

        Livewire::actingAs($this->amministratore())
            ->test(CreateProduct::class)
            ->fillForm([
                'name' => 'Maglia libero',
                'slug' => 'maglia-libero',
                'product_category_id' => $home->id,
                'altreCategorie' => [$away->id],
                'type' => ProductType::Simple->value,
                'price' => 75,
                'stock' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $libero = Product::where('slug', 'maglia-libero')->sole();
        $this->assertSame([$home->id, $away->id], $libero->idCategorie());
        $this->assertSame(['Maglia libero'], $this->nomiInCategoria($away));
    }

    #[Test]
    public function il_filtro_del_magazzino_trova_anche_le_categorie_aggiuntive(): void
    {
        $home = ProductCategory::factory()->create();
        $away = ProductCategory::factory()->create();
        $libero = $this->maglia($home, 'Maglia libero', 1);
        $libero->altreCategorie()->attach($away);
        $solo = $this->maglia($home, 'Maglia #6', 2);

        Livewire::actingAs($this->amministratore())
            ->test(MagazzinoPage::class)
            ->filterTable('product_category_id', $away->id)
            ->assertCanSeeTableRecords([$libero])
            ->assertCanNotSeeTableRecords([$solo]);
    }

    #[Test]
    public function la_migrazione_scioglie_i_pari_merito_senza_cambiare_l_ordine_dato(): void
    {
        $categoria = ProductCategory::factory()->create();
        $vecchia = $this->maglia($categoria, 'Vecchia', 0);
        $nuova = $this->maglia($categoria, 'Nuova', 0);
        $ultima = $this->maglia($categoria, 'In fondo', 7);
        $vecchia->forceFill(['created_at' => now()->subYear()])->save();

        (require database_path('migrations/2026_09_29_100000_prodotti_in_piu_categorie.php'))->up();

        $this->assertSame(
            [$nuova->id => 1, $vecchia->id => 2, $ultima->id => 3],
            Product::orderBy('sort_order')->pluck('sort_order', 'id')->all(),
        );
    }
}

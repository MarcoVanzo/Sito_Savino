<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Ti potrebbe interessare anche": gli articoli li sceglie la redazione.
 *
 * La sezione pescava quattro prodotti a caso della stessa categoria, e non
 * c'era modo di accostare la maglia di un'atleta ai suoi accessori, che
 * stanno in un altro reparto.
 */
class ArticoliCollegatiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_i_prodotti_collegati_battono_la_scelta_automatica(): void
    {
        $categoria = ProductCategory::factory()->create();
        $maglia = $this->prodotto($categoria);
        $portachiavi = $this->prodotto(ProductCategory::factory()->create());

        // Stessa categoria della maglia: senza il collegamento sarebbe questo
        // a comparire, per ripiego.
        $this->prodotto($categoria);

        $maglia->relatedProducts()->attach($portachiavi);

        $this->get(route('shop.product', $maglia))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('relatedProducts', 1)
                ->where('relatedProducts.0.id', $portachiavi->id),
            );
    }

    public function test_senza_collegamenti_resta_la_scelta_automatica(): void
    {
        $categoria = ProductCategory::factory()->create();
        $maglia = $this->prodotto($categoria);
        $vicino = $this->prodotto($categoria);

        $this->get(route('shop.product', $maglia))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('relatedProducts', 1)
                ->where('relatedProducts.0.id', $vicino->id),
            );
    }

    public function test_un_collegato_non_piu_in_vendita_non_si_mostra(): void
    {
        $maglia = $this->prodotto();
        $ritirato = $this->prodotto();
        $ritirato->update(['is_active' => false]);

        $maglia->relatedProducts()->attach($ritirato);

        $this->get(route('shop.product', $maglia))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->has('relatedProducts', 0));
    }

    public function test_il_collegamento_si_crea_dal_pannello_e_si_vede_subito(): void
    {
        $maglia = $this->prodotto();
        $portachiavi = $this->prodotto();

        // Prima visita: la cache del ripiego viene scritta.
        $this->get(route('shop.product', $maglia))->assertOk();

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $maglia->getRouteKey()])
            ->fillForm(['relatedProducts' => [$portachiavi->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        // La scelta della redazione non passa dalla cache: si vede subito,
        // non mezz'ora dopo.
        $this->get(route('shop.product', $maglia))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->has('relatedProducts', 1)
                ->where('relatedProducts.0.id', $portachiavi->id),
            );
    }

    public function test_anche_un_prodotto_nuovo_nasce_con_i_suoi_collegamenti(): void
    {
        // In creazione il prodotto non esiste ancora quando si sceglie
        // l'elenco: il legame si scrive dopo, ed e' il caso che si rompe per
        // primo se qualcuno cambia il campo.
        $portachiavi = $this->prodotto();

        Livewire::actingAs($this->superAdmin())
            ->test(CreateProduct::class)
            ->fillForm([
                'name' => 'Maglia gara 26/27',
                'slug' => 'maglia-gara-26-27',
                'product_category_id' => ProductCategory::factory()->create()->id,
                'type' => ProductType::Simple->value,
                'price' => 90,
                'stock' => 5,
                'relatedProducts' => [$portachiavi->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $maglia = Product::where('slug', 'maglia-gara-26-27')->firstOrFail();

        $this->assertSame([$portachiavi->id], $maglia->relatedProducts->pluck('id')->all());
    }

    private function prodotto(?ProductCategory $categoria = null): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'type' => ProductType::Simple,
            'product_category_id' => $categoria?->id ?? ProductCategory::factory(),
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user->refresh();
    }
}

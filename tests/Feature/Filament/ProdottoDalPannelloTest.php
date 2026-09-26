<?php

namespace Tests\Feature\Filament;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Catalogo Prodotti: prezzo scontato, SKU e giacenza.
 *
 * La redazione non riusciva a salvare né lo sconto né lo SKU di una t-shirt:
 * il campo "Prezzo Scontato" portava la regola `lte:price`, che cerca un
 * campo `price` alla radice dei dati validati mentre il modulo li tiene tutti
 * sotto `data.`. La regola non trovava il termine di confronto e falliva
 * sempre, bloccando il salvataggio dell'intero prodotto con un messaggio non
 * tradotto ("validation.lte.numeric").
 */
class ProdottoDalPannelloTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function il_prezzo_scontato_si_salva(): void
    {
        $prodotto = $this->prodotto(['price' => 20, 'sale_price' => null, 'sku' => null]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->fillForm([
                'sale_price' => 5,
                'sku' => 'TSSL-IST26',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $prodotto->refresh();

        $this->assertSame('5.00', (string) $prodotto->sale_price);
        $this->assertSame('TSSL-IST26', $prodotto->sku);
    }

    #[Test]
    public function uno_sconto_piu_alto_del_prezzo_viene_rifiutato_con_un_messaggio_leggibile(): void
    {
        $prodotto = $this->prodotto(['price' => 20]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->fillForm(['sale_price' => 30])
            ->call('save')
            ->assertHasFormErrors(['sale_price']);

        // Il messaggio non deve essere la chiave grezza della traduzione.
        $this->assertStringNotContainsString(
            'validation.lte',
            trans('validation.lte.numeric'),
        );
    }

    #[Test]
    public function la_giacenza_di_un_prodotto_con_varianti_e_la_somma_delle_taglie(): void
    {
        // `products.stock` resta al valore ereditato da WooCommerce: nessuno
        // lo aggiorna più, e il pannello mostrava quello invece della somma
        // delle taglie che il sito conta davvero.
        $prodotto = $this->prodotto(['type' => ProductType::Variable, 'stock' => 56]);

        ProductVariant::factory()->for($prodotto)->create(['size' => 'XXL', 'stock' => 5]);
        ProductVariant::factory()->for($prodotto)->create(['size' => 'XXXL', 'stock' => 9]);
        ProductVariant::factory()->for($prodotto)->create(['size' => 'S', 'stock' => 0]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->assertFormSet(['stock' => 14]);
    }

    #[Test]
    public function il_supplemento_svuotato_vale_zero_e_il_prodotto_si_salva(): void
    {
        // La colonna e' NOT NULL: il campo svuotato arrivava come null e il
        // salvataggio dell'intero prodotto falliva.
        $prodotto = $this->prodotto(['personalizzazione_prezzo' => 5]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->fillForm(['personalizzazione_prezzo' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0.00', (string) $prodotto->refresh()->personalizzazione_prezzo);
    }

    private function prodotto(array $attributi = []): Product
    {
        return Product::factory()->create([
            'product_category_id' => ProductCategory::factory(),
            'is_active' => true,
            ...$attributi,
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user->refresh();
    }
}

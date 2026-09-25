<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Models\Product;
use App\Services\StoricoPrezzi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Il prezzo barrato accanto a uno sconto è il più basso dei 30 giorni prima
 * della riduzione (art. 17-bis del Codice del consumo), non il listino.
 *
 * Fino al 25 settembre 2026 il sito barrava `products.price` e non teneva
 * nessuno storico: quattro prodotti annunciavano uno sconto rispetto a un
 * prezzo che nessuno poteva dire se fosse mai stato praticato.
 */
class StoricoPrezziTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_ogni_cambio_di_prezzo_apre_una_riga_e_chiude_la_precedente(): void
    {
        $this->travelTo(now()->subDays(10));
        $prodotto = $this->prodotto();
        $this->travelBack();

        $prodotto->update(['sale_price' => 15]);
        $prodotto->update(['name' => ['it' => 'Stesso prezzo']]);

        $righe = DB::table('storico_prezzi')->where('product_id', $prodotto->id)->orderBy('dal')->get();

        $this->assertCount(2, $righe);
        $this->assertEquals([20.0, 15.0], $righe->pluck('prezzo')->map(fn ($p) => (float) $p)->all());
        $this->assertNotNull($righe[0]->al);
        $this->assertNull($righe[1]->al);
        $this->assertTrue((bool) $righe[1]->in_sconto);
    }

    public function test_si_barra_il_prezzo_piu_basso_dei_30_giorni_prima_dello_sconto(): void
    {
        $this->travelTo(now()->subDays(40));
        $prodotto = $this->prodotto();          // 20 € per 20 giorni
        $this->travelTo(now()->addDays(20));
        $prodotto->update(['price' => 18]);     // 18 € per 20 giorni, senza sconto
        $this->travelBack();

        $prodotto->update(['sale_price' => 12]);

        $this->assertSame(18.0, app(StoricoPrezzi::class)->prezzoDiRiferimento($prodotto->fresh()));
    }

    public function test_nella_riduzione_progressiva_vale_il_prezzo_prima_della_prima(): void
    {
        $this->travelTo(now()->subDays(20));
        $prodotto = $this->prodotto();
        $this->travelTo(now()->addDays(10));
        $prodotto->update(['sale_price' => 15]);
        $this->travelBack();

        $prodotto->update(['sale_price' => 10]);

        $this->assertSame(20.0, app(StoricoPrezzi::class)->prezzoDiRiferimento($prodotto->fresh()));
    }

    public function test_un_prodotto_nato_in_sconto_non_barra_niente_ma_si_vende_scontato(): void
    {
        $prodotto = $this->prodotto(['sale_price' => 50, 'price' => 75]);

        $this->assertNull(app(StoricoPrezzi::class)->prezzoDiRiferimento($prodotto));

        $this->get(route('shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('product.sale_price', null)
                ->where('product.price', '50.00')
                ->where('product.prezzo_piu_basso_30_giorni', false));
    }

    public function test_uno_sconto_piu_alto_del_minimo_recente_non_si_annuncia(): void
    {
        $this->travelTo(now()->subDays(20));
        $prodotto = $this->prodotto(['sale_price' => 8]);   // nato a 8
        $this->travelTo(now()->addDays(5));
        $prodotto->update(['sale_price' => null]);          // poi 20
        $this->travelBack();

        $prodotto->update(['sale_price' => 12]);            // "sconto" a 12, ma 8 è di 15 giorni fa

        $this->assertNull(app(StoricoPrezzi::class)->prezzoDiRiferimento($prodotto->fresh()));
    }

    public function test_uno_sconto_programmato_lo_registra_il_giro_orario(): void
    {
        $this->travelTo(now()->subDays(10));
        $prodotto = $this->prodotto(['sale_price' => 10, 'sale_start' => now()->addDays(5)]);
        $this->travelBack();

        $this->assertSame(1, DB::table('storico_prezzi')->where('product_id', $prodotto->id)->count());

        $this->artisan('prezzi:registra')->assertSuccessful();

        $this->assertSame(20.0, app(StoricoPrezzi::class)->prezzoDiRiferimento($prodotto->fresh()));
    }

    private function prodotto(array $attributi = []): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'type' => ProductType::Simple,
            'price' => 20,
            'sale_price' => null,
            ...$attributi,
        ]);
    }
}

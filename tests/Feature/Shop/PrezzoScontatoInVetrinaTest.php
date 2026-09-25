<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Lo sconto si annuncia in vetrina solo mentre è davvero in corso.
 *
 * Il carrello e l'ordine passano da `Product::effectivePrice()`, che rispetta
 * la finestra `sale_start`/`sale_end`; la pagina del prodotto riceveva invece
 * `sale_price` nuda e la mostrava sempre. Uno sconto programmato per il mese
 * prossimo si vedeva già oggi, e al momento di pagare tornava il prezzo pieno.
 */
class PrezzoScontatoInVetrinaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_lo_sconto_in_corso_arriva_alla_pagina(): void
    {
        // Prima al prezzo pieno, poi scontato: senza un prezzo praticato prima
        // lo sconto non si annuncia (art. 17-bis, StoricoPrezziTest).
        $this->travelTo(now()->subWeek());
        $prodotto = $this->prodotto([]);
        $this->travelBack();

        $prodotto->update([
            'sale_price' => 5,
            'sale_start' => now()->subMinute(),
            'sale_end' => now()->addMonth(),
        ]);

        $this->get(route('shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('product.sale_price', '5.00')->where('product.price', '20.00'));
    }

    public function test_uno_sconto_futuro_non_si_vede(): void
    {
        $prodotto = $this->prodotto([
            'sale_price' => 5,
            'sale_start' => now()->addWeek(),
            'sale_end' => now()->addMonth(),
        ]);

        $this->get(route('shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('product.sale_price', null));

        $this->assertSame(20.0, $prodotto->effectivePrice());
    }

    public function test_uno_sconto_scaduto_non_si_vede_nemmeno_nella_griglia(): void
    {
        $this->prodotto([
            'sale_price' => 5,
            'sale_start' => now()->subMonth(),
            'sale_end' => now()->subDay(),
        ]);

        $this->get(route('shop'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('allProducts.0.sale_price', null));
    }

    private function prodotto(array $attributi): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'type' => ProductType::Simple,
            'price' => 20,
            ...$attributi,
        ]);
    }
}

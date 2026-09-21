<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\GuidaTaglie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * La guida alle taglie cambia da prodotto a prodotto.
 *
 * Il link sotto le taglie apriva una tabella scritta dentro il componente
 * Vue, uguale per una maglia gara e per una t-shirt, e per i prodotti vecchi
 * — quelli per cui una guida non esiste — non si poteva togliere.
 */
class GuidaTaglieDelProdottoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_senza_documenti_caricati_la_voce_non_compare(): void
    {
        $prodotto = $this->prodotto();

        $this->get(route('shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('product.size_guide_url', null));
    }

    public function test_con_i_documenti_caricati_la_voce_porta_alla_pagina_generale(): void
    {
        $this->caricaIDocumenti();

        $prodotto = $this->prodotto();

        $this->get(route('shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('product.size_guide_url', route('shop.size-guide')));
    }

    public function test_un_prodotto_puo_avere_il_suo_documento(): void
    {
        $this->caricaIDocumenti();

        $prodotto = $this->prodotto(['size_guide' => 'size-guides/maglie-gara.pdf']);

        $this->get(route('shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where(
                'product.size_guide_url',
                fn ($url) => is_string($url) && str_contains($url, 'maglie-gara.pdf'),
            ));
    }

    public function test_la_voce_si_puo_togliere_dal_singolo_prodotto(): void
    {
        $this->caricaIDocumenti();

        $prodotto = $this->prodotto(['size_guide' => GuidaTaglie::NASCOSTA]);

        $this->get(route('shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('product.size_guide_url', null));
    }

    public function test_le_scelte_del_pannello_sono_i_documenti_caricati(): void
    {
        $this->caricaIDocumenti();

        $opzioni = GuidaTaglie::opzioni();

        $this->assertArrayHasKey(GuidaTaglie::NASCOSTA, $opzioni);
        $this->assertSame('maglie-gara', $opzioni['size-guides/maglie-gara.pdf']);
        $this->assertSame('t-shirt', $opzioni['size-guides/t-shirt.pdf']);
    }

    private function caricaIDocumenti(): void
    {
        SiteSetting::set('shop.size_guides', json_encode([
            'size-guides/maglie-gara.pdf',
            'size-guides/t-shirt.pdf',
        ]));

        SiteSetting::clearCache();
    }

    private function prodotto(array $attributi = []): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'type' => ProductType::Simple,
            ...$attributi,
        ]);
    }
}

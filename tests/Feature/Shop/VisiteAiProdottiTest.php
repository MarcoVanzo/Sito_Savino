<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShopEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «Prodotti più visti» restava vuoto: le visite si registravano col solo
 * indirizzo, senza il prodotto, e fra le visite finivano le chiamate JSON del
 * carrello e i prefetch del menu.
 */
class VisiteAiProdottiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    #[Test]
    public function la_scheda_di_un_prodotto_registra_il_prodotto_visto(): void
    {
        $maglia = $this->prodotto();

        $this->get(route('shop.product', $maglia))->assertOk();

        $visita = ShopEvent::views()->sole();
        $this->assertSame($maglia->getMorphClass(), $visita->viewable_type);
        $this->assertSame($maglia->id, (int) $visita->viewable_id);
    }

    #[Test]
    public function la_scheda_servita_dalla_cache_conta_lo_stesso(): void
    {
        $maglia = $this->prodotto();

        // Scalda la cache senza contare. Una richiesta sola che conta: nei
        // test le callback afterResponse restano nell'app e si ripeterebbero.
        $this->get(route('shop.product', $maglia), ['Purpose' => 'prefetch'])->assertHeader('X-Page-Cache', 'MISS');
        $this->get(route('shop.product', $maglia))->assertHeader('X-Page-Cache', 'HIT');

        $visita = ShopEvent::views()->sole();
        $this->assertSame($maglia->id, (int) $visita->viewable_id);
    }

    #[Test]
    public function le_chiamate_del_carrello_e_i_prefetch_non_sono_visite(): void
    {
        $this->get(route('shop.cart.count'))->assertOk();
        $this->get(route('shop.cart.data'))->assertOk();
        $this->get(route('shop'), ['Purpose' => 'prefetch'])->assertOk();

        $this->assertSame(0, ShopEvent::views()->count());

        $this->get(route('shop'))->assertOk();

        $this->assertSame(1, ShopEvent::views()->count());
    }

    #[Test]
    public function le_visite_gia_registrate_ritrovano_il_prodotto_dall_indirizzo(): void
    {
        $maglia = $this->prodotto();
        $visita = fn (string $url) => ShopEvent::create([
            'event_type' => 'view',
            'metadata' => ['url' => $url, 'referer' => null],
        ]);

        $italiana = $visita('https://savinodelbenevolley.it/shop/prodotto/'.$maglia->slug);
        $inglese = $visita('https://savinodelbenevolley.it/en/shop/product/'.$maglia->slug.'?utm_source=ig');
        $sconosciuto = $visita('https://savinodelbenevolley.it/shop/prodotto/non-esiste');
        $vetrina = $visita('https://savinodelbenevolley.it/shop');

        $migrazione = require database_path('migrations/2026_10_11_160000_le_visite_ai_prodotti_dicono_quale_prodotto.php');
        $migrazione->up();

        foreach ([$italiana, $inglese] as $evento) {
            $this->assertSame($maglia->id, (int) $evento->fresh()->viewable_id);
            $this->assertSame($maglia->getMorphClass(), $evento->fresh()->viewable_type);
        }
        $this->assertNull($sconosciuto->fresh()->viewable_id);
        $this->assertNull($vetrina->fresh()->viewable_id);
    }

    #[Test]
    public function le_chiamate_del_carrello_gia_registrate_si_tolgono(): void
    {
        $visita = fn (string $url) => ShopEvent::create([
            'event_type' => 'view',
            'metadata' => ['url' => $url, 'referer' => null],
        ]);

        $visita('https://savinodelbenevolley.it/shop/carrello/count');
        $visita('https://savinodelbenevolley.it/shop/carrello/data?x=1');
        $visita('https://savinodelbenevolley.it/en/shop/cart/count');
        $carrello = $visita('https://savinodelbenevolley.it/shop/carrello');
        $vetrina = $visita('https://savinodelbenevolley.it/en/shop');

        $migrazione = require database_path('migrations/2026_10_11_160100_le_chiamate_del_carrello_non_sono_visite.php');
        $migrazione->up();

        $this->assertEqualsCanonicalizing(
            [$carrello->id, $vetrina->id],
            ShopEvent::views()->pluck('id')->all(),
        );
    }

    private function prodotto(): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'type' => ProductType::Simple,
            'product_category_id' => ProductCategory::factory(),
        ]);
    }
}

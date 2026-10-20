<?php

namespace Tests\Feature\Shop;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `/en/shop/product/…` mostrava nome e descrizione italiani ed era dichiarata
 * `en` negli hreflang (audit SEO del 20/10/2026). Finche' la redazione non la
 * traduce, la scheda inglese non si propone a Google.
 */
class SchedeProdottoSenzaTraduzioneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function prodotto(array $nome, array $descrizione = []): Product
    {
        return Product::factory()->create([
            'name' => $nome,
            'description' => $descrizione,
            'short_description' => [],
            'slug' => 'prodotto-'.fake()->unique()->numberBetween(1, 99999),
            'stock' => 5,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function un_prodotto_e_tradotto_solo_con_nome_e_descrizione_in_inglese(): void
    {
        $this->assertFalse($this->prodotto(['it' => 'Sciarpa'])->tradottoIn('en'));
        $this->assertFalse($this->prodotto(['it' => 'Sciarpa', 'en' => 'Scarf'], ['it' => 'Lana'])->tradottoIn('en'));
        $this->assertTrue($this->prodotto(['it' => 'Sciarpa', 'en' => 'Scarf'], ['it' => 'Lana', 'en' => 'Wool'])->tradottoIn('en'));
        $this->assertTrue($this->prodotto(['it' => 'Peluche', 'en' => 'Plush'])->tradottoIn('en'));
        $this->assertTrue($this->prodotto(['it' => 'Sciarpa'])->tradottoIn('it'));
    }

    #[Test]
    public function la_scheda_senza_traduzione_non_dichiara_l_inglese_e_in_inglese_e_noindex(): void
    {
        $prodotto = $this->prodotto(['it' => 'Sciarpa'], ['it' => 'Lana']);

        $italiana = $this->get(route('shop.product', $prodotto->slug))->assertOk()->getContent();
        $this->assertStringNotContainsString('hreflang="en"', $italiana);
        $this->assertStringNotContainsString('noindex', $italiana);

        $inglese = $this->get(route('en.shop.product', $prodotto->slug))->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $inglese);
    }

    #[Test]
    public function la_scheda_tradotta_resta_nelle_due_lingue(): void
    {
        $prodotto = $this->prodotto(['it' => 'Sciarpa', 'en' => 'Scarf'], ['it' => 'Lana', 'en' => 'Wool']);

        $inglese = $this->get(route('en.shop.product', $prodotto->slug))->assertOk()->getContent();

        $this->assertStringContainsString('hreflang="it"', $inglese);
        $this->assertStringContainsString('hreflang="en"', $inglese);
        $this->assertStringNotContainsString('noindex', $inglese);
    }

    #[Test]
    public function la_sitemap_elenca_in_inglese_solo_i_prodotti_tradotti(): void
    {
        $tradotto = $this->prodotto(['it' => 'Sciarpa', 'en' => 'Scarf'], ['it' => 'Lana', 'en' => 'Wool']);
        $soloItaliano = $this->prodotto(['it' => 'Cappellino'], ['it' => 'Cotone']);

        $xml = $this->get('/sitemap-prodotti.xml')->assertOk()->getContent();

        $this->assertStringContainsString(route('en.shop.product', $tradotto->slug), $xml);
        $this->assertStringContainsString(route('shop.product', $soloItaliano->slug), $xml);
        $this->assertStringNotContainsString(route('en.shop.product', $soloItaliano->slug), $xml);
    }
}

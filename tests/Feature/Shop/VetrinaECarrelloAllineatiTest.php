<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CartService;
use App\Services\StoricoPrezzi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Quello che la vetrina e il carrello mostrano deve essere vero nel momento in
 * cui lo si guarda: sconto, etichette e giacenza (revisione dello shop del
 * 26/09/2026). Una card in cache che dice IN OFFERTA a sconto finito, o un "+"
 * che non conta la riga con la firma, sono il prezzo o la scarsita' che il
 * carrello poi smentisce.
 */
class VetrinaECarrelloAllineatiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    // --- Cache della vetrina ----------------------------------------------

    public function test_uno_sconto_finito_da_solo_esce_dalla_vetrina_al_giro_orario(): void
    {
        $prodotto = $this->scontatoDopoIlPrezzoPieno(['sale_end' => now()->addMinutes(2)]);

        $this->get(route('shop'))->assertInertia(fn ($page) => $page
            ->where('allProducts.0.prezzo_piu_basso_30_giorni', true));

        // Dentro i dieci minuti della cache: senza il giro che la butta, la
        // card continuerebbe a dire IN OFFERTA.
        $this->travel(5)->minutes();
        $this->artisan('prezzi:registra')->assertSuccessful();

        $this->get(route('shop'))->assertInertia(fn ($page) => $page
            ->where('allProducts.0.id', $prodotto->id)
            ->where('allProducts.0.prezzo_piu_basso_30_giorni', false)
            ->where('allProducts.0.price', '20.00')
            ->where('allProducts.0.etichette', ['nuovo']));
    }

    public function test_un_movimento_di_magazzino_butta_la_vetrina(): void
    {
        $prodotto = $this->prodotto(['stock' => 2]);
        Cache::put('public:shop:it', 'vecchia', 600);

        StockMovement::create([
            'product_id' => $prodotto->id,
            'quantity' => -1,
            'type' => StockMovementType::Sale,
        ]);

        $this->assertFalse(Cache::has('public:shop:it'));
    }

    public function test_la_vetrina_si_butta_solo_a_transazione_chiusa(): void
    {
        // Buttata dentro la transazione del checkout, una richiesta
        // concorrente la ricostruiva con la giacenza non ancora scritta.
        $prodotto = $this->prodotto(['stock' => 2]);
        Cache::put('public:shop:it', 'vecchia', 600);

        DB::transaction(function () use ($prodotto) {
            StockMovement::create([
                'product_id' => $prodotto->id,
                'quantity' => -1,
                'type' => StockMovementType::Sale,
            ]);

            $this->assertTrue(Cache::has('public:shop:it'));
        });

        $this->assertFalse(Cache::has('public:shop:it'));
    }

    public function test_una_taglia_modificata_butta_la_vetrina(): void
    {
        $prodotto = $this->prodotto(['type' => ProductType::Variable]);
        $taglia = ProductVariant::factory()->for($prodotto)->create(['stock' => 3]);
        Cache::put('public:shop:it', 'vecchia', 600);

        $taglia->update(['stock' => 1]);

        $this->assertFalse(Cache::has('public:shop:it'));
    }

    public function test_i_correlati_automatici_mostrano_il_prezzo_di_adesso(): void
    {
        $scheda = $this->prodotto();
        $correlato = $this->prodotto(['price' => 30]);

        $this->get(route('shop.product', $scheda))->assertInertia(fn ($page) => $page
            ->where('relatedProducts.0.id', $correlato->id)
            ->where('relatedProducts.0.price', '30.00'));

        $correlato->update(['price' => 25]);

        $this->get(route('shop.product', $scheda))->assertInertia(fn ($page) => $page
            ->where('relatedProducts.0.price', '25.00'));
    }

    // --- Storico dei prezzi -----------------------------------------------

    public function test_registra_dice_se_ha_aperto_una_riga(): void
    {
        $prodotto = $this->prodotto();
        $storico = app(StoricoPrezzi::class);

        // L'observer ha gia' registrato il prezzo di nascita.
        $this->assertFalse($storico->registra($prodotto));

        $prodotto->forceFill(['sale_price' => 15])->saveQuietly();

        $this->assertTrue($storico->registra($prodotto));
        $this->assertFalse($storico->registra($prodotto));
        $this->assertSame(1, DB::table('storico_prezzi')->where('product_id', $prodotto->id)->whereNull('al')->count());
    }

    public function test_la_vetrina_legge_lo_storico_con_una_query_sola(): void
    {
        $prodotti = collect(range(1, 3))->map(fn () => $this->scontatoDopoIlPrezzoPieno());
        $this->prodotto(); // uno non in sconto: non chiede niente

        $storico = app(StoricoPrezzi::class);
        $righe = $storico->righePer(Product::all());

        foreach ($prodotti as $prodotto) {
            $this->assertSame(20.0, $storico->prezzoDiRiferimento($prodotto, $righe[$prodotto->id]));
            $this->assertSame($storico->prezzoDiRiferimento($prodotto), $storico->prezzoDiRiferimento($prodotto, $righe[$prodotto->id]));
        }

        $query = 0;
        DB::listen(function ($evento) use (&$query) {
            if (str_contains($evento->sql, 'storico_prezzi')) {
                $query++;
            }
        });

        $this->get(route('shop'))->assertOk();

        $this->assertSame(1, $query);
    }

    // --- Carrello ---------------------------------------------------------

    public function test_tolta_la_firma_le_righe_dello_stesso_pezzo_si_uniscono(): void
    {
        $this->actingAs(User::factory()->create());
        $prodotto = $this->prodottoConFirma();

        $this->post(route('shop.cart.store'), ['product_id' => $prodotto->id, 'quantity' => 1, 'personalizzazione' => true]);
        $this->post(route('shop.cart.store'), ['product_id' => $prodotto->id, 'quantity' => 2]);

        $prodotto->update(['personalizzazione_nome' => null]);

        $righe = app(CartService::class)->getCart()->items;

        $this->assertCount(1, $righe);
        $this->assertSame(3, $righe->first()->quantity);
        $this->assertFalse($righe->first()->con_personalizzazione);
    }

    public function test_riaccesa_la_firma_la_vecchia_riga_non_torna_a_pagarla(): void
    {
        $this->actingAs(User::factory()->create());
        $prodotto = $this->prodottoConFirma();

        $this->post(route('shop.cart.store'), ['product_id' => $prodotto->id, 'quantity' => 1, 'personalizzazione' => true]);

        $prodotto->update(['personalizzazione_nome' => null]);
        app(CartService::class)->getCart();

        $prodotto->update(['personalizzazione_nome' => ['it' => 'Firma della giocatrice']]);
        $servizio = app(CartService::class);
        $servizio->invalidateCache();

        $this->assertSame(50.0, $servizio->getCartTotal());
    }

    public function test_il_piu_e_l_avviso_contano_anche_la_riga_con_la_firma(): void
    {
        $this->actingAs(User::factory()->create());
        $prodotto = $this->prodottoConFirma(['stock' => 3]);

        $this->post(route('shop.cart.store'), ['product_id' => $prodotto->id, 'quantity' => 1, 'personalizzazione' => true]);
        $this->post(route('shop.cart.store'), ['product_id' => $prodotto->id, 'quantity' => 1]);

        $this->getJson(route('shop.cart.data'))
            ->assertJsonPath('items.0.disponibili', 2)
            ->assertJsonPath('items.1.disponibili', 2)
            ->assertJsonPath('items.0.stock_warning', false);

        // Ne resta uno solo: nessuna delle due righe da sola lo supera, la
        // somma si'.
        DB::table('products')->where('id', $prodotto->id)->update(['stock' => 1]);

        $this->getJson(route('shop.cart.data'))
            ->assertJsonPath('items.0.stock_warning', true)
            ->assertJsonPath('items.1.stock_warning', true)
            ->assertJsonPath('items.0.disponibili', 0);
    }

    private function prodotto(array $attributi = []): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'type' => ProductType::Simple,
            'product_category_id' => null,
            'price' => 20,
            'sale_price' => null,
            'stock' => 10,
            ...$attributi,
        ]);
    }

    private function prodottoConFirma(array $attributi = []): Product
    {
        return $this->prodotto([
            'price' => 50,
            'personalizzazione_nome' => ['it' => 'Firma della giocatrice', 'en' => 'Player signature'],
            'personalizzazione_prezzo' => 5,
            ...$attributi,
        ]);
    }

    /**
     * Venduto una settimana a prezzo pieno, poi scontato: e' cio' che rende lo
     * sconto annunciabile (StoricoPrezzi).
     */
    private function scontatoDopoIlPrezzoPieno(array $sconto = []): Product
    {
        $this->travelTo(now()->subWeek());
        $prodotto = $this->prodotto();
        $this->travelBack();

        $prodotto->update(['sale_price' => 15, 'sale_start' => now()->subMinute(), ...$sconto]);

        return $prodotto->fresh();
    }
}

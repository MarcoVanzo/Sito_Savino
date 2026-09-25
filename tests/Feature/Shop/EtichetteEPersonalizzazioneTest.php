<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Filament\Resources\ProductResource;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\EtichetteDelProdotto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Due richieste della redazione dello shop: scegliere le etichette sulla foto
 * dei prodotti e offrire la personalizzazione (la firma della giocatrice) con
 * un supplemento.
 */
class EtichetteEPersonalizzazioneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    /**
     * Il carrello di un ospite si ritrova dall'id di sessione, che nel client
     * dei test cambia a ogni richiesta: con un utente si ritrova da user_id.
     */
    private function cliente(): void
    {
        $this->actingAs(User::factory()->create());
    }

    // --- Etichette ---------------------------------------------------------

    public function test_senza_scelta_le_etichette_restano_automatiche(): void
    {
        $nuovo = $this->scontatoDopoIlPrezzoPieno();
        $vecchio = $this->prodotto();
        $vecchio->forceFill(['created_at' => now()->subMonths(3)])->save();

        $this->assertSame(['nuovo', 'in_offerta'], EtichetteDelProdotto::per($nuovo));
        $this->assertSame([], EtichetteDelProdotto::per($vecchio));
    }

    public function test_la_scelta_della_redazione_vince_e_nell_ordine_dato(): void
    {
        $prodotto = $this->prodotto(['etichette' => ['hot_sales', 'nuovo']]);
        $prodotto->forceFill(['created_at' => now()->subYear()])->save();

        $this->assertSame(['hot_sales', 'nuovo'], EtichetteDelProdotto::per($prodotto));
    }

    public function test_un_elenco_vuoto_spegne_anche_le_automatiche(): void
    {
        $prodotto = $this->prodotto(['etichette' => [], 'sale_price' => 15]);

        $this->assertSame([], EtichetteDelProdotto::per($prodotto));
    }

    public function test_in_offerta_senza_prezzo_di_riferimento_non_compare(): void
    {
        // Nato gia' scontato: nessun prezzo praticato prima, quindi lo sconto
        // non si annuncia (art. 17-bis), come il prezzo barrato.
        $prodotto = $this->prodotto(['etichette' => ['in_offerta'], 'sale_price' => 15]);

        $this->assertSame([], EtichetteDelProdotto::per($prodotto));
    }

    public function test_in_offerta_senza_sconto_in_corso_non_compare(): void
    {
        $prodotto = $this->prodotto(['etichette' => ['in_offerta', 'hot_sales']]);

        $this->assertSame(['hot_sales'], EtichetteDelProdotto::per($prodotto));
    }

    public function test_ultimo_rimasto_compare_solo_con_un_pezzo_per_taglia(): void
    {
        $semplice = $this->prodotto(['etichette' => ['ultimo_rimasto'], 'stock' => 3]);
        $this->assertSame([], EtichetteDelProdotto::per($semplice));

        $semplice->update(['stock' => 1]);
        $this->assertSame(['ultimo_rimasto'], EtichetteDelProdotto::per($semplice->fresh()));

        // Le maglie gara: una per atleta e taglia.
        $maglia = $this->prodotto(['etichette' => ['ultimo_rimasto'], 'type' => ProductType::Variable, 'stock' => 0]);
        ProductVariant::factory()->create(['product_id' => $maglia->id, 'size' => '#2 L', 'stock' => 1]);
        ProductVariant::factory()->create(['product_id' => $maglia->id, 'size' => '#6 L', 'stock' => 0]);
        $this->assertSame(['ultimo_rimasto'], EtichetteDelProdotto::per($maglia->fresh()));

        ProductVariant::factory()->create(['product_id' => $maglia->id, 'size' => '#7 S', 'stock' => 4]);
        $this->assertSame([], EtichetteDelProdotto::per($maglia->fresh()));
    }

    public function test_un_prodotto_esaurito_tiene_solo_nuovo(): void
    {
        $prodotto = $this->prodotto(['etichette' => ['hot_sales', 'nuovo'], 'stock' => 0]);

        $this->assertSame(['nuovo'], EtichetteDelProdotto::per($prodotto));
    }

    public function test_le_etichette_arrivano_alla_griglia_e_alla_scheda(): void
    {
        $prodotto = $this->prodotto(['etichette' => ['hot_sales']]);

        $this->get(route('shop'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('allProducts.0.etichette', ['hot_sales']));

        $this->get(route('shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('product.etichette', ['hot_sales']));
    }

    public function test_riaccendere_le_automatiche_azzera_la_scelta(): void
    {
        $this->assertSame(
            ['etichette' => null],
            ProductResource::etichetteDalModulo(['etichette_automatiche' => true]),
        );
        $this->assertSame(
            ['etichette' => ['nuovo']],
            ProductResource::etichetteDalModulo(['etichette_automatiche' => false, 'etichette' => ['nuovo']]),
        );
        $this->assertSame(
            ['etichette' => []],
            ProductResource::etichetteDalModulo(['etichette_automatiche' => false]),
        );
    }

    // --- Personalizzazione -------------------------------------------------

    public function test_la_scheda_offre_la_personalizzazione_solo_se_ha_un_nome(): void
    {
        $con = $this->prodottoConFirma();
        $senza = $this->prodotto();

        $this->get(route('shop.product', $con))
            ->assertInertia(fn ($page) => $page
                ->where('product.personalizzazione.nome', 'Firma della giocatrice')
                ->where('product.personalizzazione.prezzo', 5));

        $this->get(route('shop.product', $senza))
            ->assertInertia(fn ($page) => $page->where('product.personalizzazione', null));
    }

    public function test_il_supplemento_entra_nel_prezzo_della_riga(): void
    {
        $this->cliente();
        $prodotto = $this->prodottoConFirma();

        $this->post(route('shop.cart.store'), [
            'product_id' => $prodotto->id,
            'quantity' => 2,
            'personalizzazione' => true,
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(110.0, app(CartService::class)->getCartTotal());
        $this->getJson(route('shop.cart.data'))
            ->assertJsonPath('items.0.price', 55)
            ->assertJsonPath('items.0.personalizzazione', 'Firma della giocatrice');
    }

    public function test_con_e_senza_firma_sono_due_righe_ma_la_giacenza_e_una(): void
    {
        $this->cliente();
        $prodotto = $this->prodottoConFirma(['stock' => 1]);

        $this->post(route('shop.cart.store'), ['product_id' => $prodotto->id, 'quantity' => 1]);
        $this->post(route('shop.cart.store'), [
            'product_id' => $prodotto->id,
            'quantity' => 1,
            'personalizzazione' => true,
        ])->assertSessionHas('error');

        $this->assertSame(1, app(CartService::class)->getItemCount());
    }

    public function test_non_si_chiede_la_firma_a_un_prodotto_che_non_la_offre(): void
    {
        $this->cliente();
        $prodotto = $this->prodotto();

        $this->post(route('shop.cart.store'), [
            'product_id' => $prodotto->id,
            'quantity' => 1,
            'personalizzazione' => true,
        ])->assertSessionHas('error', __('messages.cart.personalization_unavailable'));

        $this->assertSame(0, app(CartService::class)->getItemCount());
    }

    public function test_l_ordine_fotografa_nome_e_supplemento(): void
    {
        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 7, 'free_threshold' => null]);
        $prodotto = $this->prodottoConFirma();

        $cart = Cart::create(['session_id' => 'sessione-firma', 'expires_at' => now()->addDay()]);
        $cart->items()->create(['product_id' => $prodotto->id, 'quantity' => 1, 'con_personalizzazione' => true]);
        $cart->items()->create(['product_id' => $prodotto->id, 'quantity' => 1, 'con_personalizzazione' => false]);

        $order = app(CheckoutService::class)->createOrder($cart->fresh(), $this->datiDelCheckout());

        $firmata = $order->items->firstWhere('personalizzazione', '!=', null);
        $semplice = $order->items->firstWhere('personalizzazione', null);

        $this->assertSame('55.00', $firmata->price_at_time_of_purchase);
        $this->assertSame('5.00', $firmata->supplemento_personalizzazione);
        $this->assertSame('Firma della giocatrice', $firmata->personalizzazioneIn('it'));
        $this->assertSame('Player signature', $firmata->personalizzazioneIn('en'));
        $this->assertSame('50.00', $semplice->price_at_time_of_purchase);

        // Rinominarla dopo non cambia cosa e' stato comprato.
        $prodotto->update(['personalizzazione_nome' => ['it' => 'Autografo']]);
        $this->assertSame('Firma della giocatrice', $firmata->fresh()->personalizzazioneIn('it'));
    }

    public function test_tolta_dal_prodotto_la_riga_nel_carrello_torna_semplice(): void
    {
        $this->cliente();
        $prodotto = $this->prodottoConFirma();
        $this->post(route('shop.cart.store'), [
            'product_id' => $prodotto->id,
            'quantity' => 1,
            'personalizzazione' => true,
        ]);

        $prodotto->update(['personalizzazione_nome' => null]);

        $this->assertSame(50.0, app(CartService::class)->getCartTotal());
    }

    private function prodotto(array $attributi = []): Product
    {
        return Product::factory()->create([
            'is_active' => true,
            'type' => ProductType::Simple,
            'price' => 20,
            'stock' => 10,
            ...$attributi,
        ]);
    }

    /**
     * Venduto una settimana a prezzo pieno, poi scontato: e' cio' che rende lo
     * sconto annunciabile (StoricoPrezzi).
     */
    private function scontatoDopoIlPrezzoPieno(): Product
    {
        $this->travelTo(now()->subWeek());
        $prodotto = $this->prodotto();
        $this->travelBack();

        $prodotto->update(['sale_price' => 15, 'sale_start' => now()->subMinute()]);

        return $prodotto->fresh();
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
     * @return array<string, mixed>
     */
    private function datiDelCheckout(): array
    {
        $indirizzo = [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'street' => 'Via Roma 1',
            'city' => 'Firenze',
            'zip_code' => '50100',
            'province' => 'FI',
        ];

        return [
            'guest_name' => 'Mario Rossi',
            'guest_email' => 'mario@example.com',
            'country' => 'IT',
            'shipping_address' => $indirizzo,
            'billing_address' => $indirizzo,
            'payment_gateway' => 'stripe',
            'privacy_accepted_at' => now(),
        ];
    }
}

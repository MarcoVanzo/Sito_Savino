<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Mail\OrderConfirmation;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le condizioni di vendita vendono maglie da gara e autografati «nello stato
 * descritto nella scheda». Lo stato e' obbligatorio per quegli articoli, si
 * legge nella scheda e l'ordine lo fotografa al momento dell'acquisto.
 */
class StatoDellArticoloTest extends TestCase
{
    use RefreshDatabase;

    private const STATO_IT = "Indossata in gara il 12/10/2025.\nSegni di gioco, non lavata, autografo originale.";

    private const STATO_EN = 'Worn in a match on 12/10/2025. Signs of play, unwashed, original autograph.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    // --- Pannello ----------------------------------------------------------

    public function test_acceso_il_flag_lo_stato_e_obbligatorio(): void
    {
        $prodotto = $this->prodotto();

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->fillForm(['usato_o_autografato' => true, 'stato_articolo' => ''])
            ->call('save')
            ->assertHasFormErrors(['stato_articolo']);

        $this->assertFalse($prodotto->fresh()->usato_o_autografato);
    }

    public function test_con_lo_stato_il_prodotto_si_salva(): void
    {
        $prodotto = $this->prodotto();

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->fillForm(['usato_o_autografato' => true, 'stato_articolo' => self::STATO_IT])
            ->call('save')
            ->assertHasNoFormErrors();

        $prodotto->refresh();
        $this->assertTrue($prodotto->usato_o_autografato);
        $this->assertSame(self::STATO_IT, $prodotto->getTranslation('stato_articolo', 'it'));
    }

    public function test_un_prodotto_nuovo_non_nasce_senza_stato(): void
    {
        $categoria = ProductCategory::factory()->create();

        Livewire::actingAs($this->superAdmin())
            ->test(CreateProduct::class)
            ->fillForm([
                'name' => 'Maglia gara Bosetti #9',
                'slug' => 'maglia-gara-bosetti-9',
                'product_category_id' => $categoria->id,
                'type' => ProductType::Simple->value,
                'price' => 75,
                'stock' => 1,
                'usato_o_autografato' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['stato_articolo']);

        $this->assertDatabaseMissing('products', ['slug' => 'maglia-gara-bosetti-9']);
    }

    public function test_senza_flag_lo_stato_resta_facoltativo(): void
    {
        $prodotto = $this->prodotto();

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->fillForm(['sku' => 'TS-001'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('TS-001', $prodotto->fresh()->sku);
    }

    public function test_salvando_in_inglese_basta_lo_stato_italiano_in_archivio(): void
    {
        // L'inglese ripiega sull'italiano: non tradurre lo stato non deve
        // bloccare il salvataggio della scheda inglese.
        $prodotto = $this->prodotto(['usato_o_autografato' => true, 'stato_articolo' => ['it' => self::STATO_IT]]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->set('activeLocale', 'en')
            ->fillForm(['name' => 'Match jersey Bosetti #9'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Match jersey Bosetti #9', $prodotto->fresh()->getTranslation('name', 'en'));
    }

    public function test_salvando_in_inglese_senza_stato_italiano_si_chiede_l_italiano(): void
    {
        $prodotto = $this->prodotto(['usato_o_autografato' => true]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->set('activeLocale', 'en')
            ->fillForm(['name' => 'Match jersey Bosetti #9', 'stato_articolo' => self::STATO_EN])
            ->call('save')
            ->assertHasFormErrors(['stato_articolo']);
    }

    public function test_lo_stato_non_tradotto_non_butta_la_scheda_inglese(): void
    {
        // Con un ->required() sul campo, il plugin translatable avrebbe
        // rivalidato i dati inglesi senza stato e scartato in silenzio anche
        // il nome inglese appena scritto.
        $prodotto = $this->prodotto(['usato_o_autografato' => true, 'stato_articolo' => ['it' => self::STATO_IT]]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->set('activeLocale', 'en')
            ->fillForm(['name' => 'Match jersey Bosetti #9'])
            ->set('activeLocale', 'it')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Match jersey Bosetti #9', $prodotto->fresh()->getTranslation('name', 'en', false));
    }

    // --- Scheda ------------------------------------------------------------

    public function test_la_scheda_mostra_lo_stato_nella_lingua_con_ripiego(): void
    {
        $prodotto = $this->magliaIndossata(['stato_articolo' => ['it' => self::STATO_IT]]);

        $this->get(route('shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('product.stato_articolo', self::STATO_IT));

        // Non tradotto: l'inglese legge l'italiano invece di un riquadro vuoto.
        $this->get(route('en.shop.product', $prodotto))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('product.stato_articolo', self::STATO_IT));
    }

    public function test_un_articolo_nuovo_non_ha_il_riquadro(): void
    {
        // Uno stato rimasto scritto dopo aver spento il flag non si mostra.
        $prodotto = $this->prodotto(['stato_articolo' => ['it' => self::STATO_IT]]);

        $this->get(route('shop.product', $prodotto))
            ->assertInertia(fn ($page) => $page->where('product.stato_articolo', null));
    }

    // --- Ordine ------------------------------------------------------------

    public function test_la_riga_d_ordine_fotografa_lo_stato(): void
    {
        $maglia = $this->magliaIndossata();
        $nuova = $this->prodotto(['stato_articolo' => ['it' => 'non deve finire nell\'ordine']]);
        $order = $this->ordina($maglia, $nuova);

        $riga = $order->items->firstWhere('product_id', $maglia->id);
        $semplice = $order->items->firstWhere('product_id', $nuova->id);

        $this->assertSame(self::STATO_IT, $riga->statoArticoloIn('it'));
        $this->assertSame(self::STATO_EN, $riga->statoArticoloIn('en'));
        $this->assertNull($semplice->stato_articolo);

        // Riscrivere la scheda dopo non cambia cosa e' stato venduto.
        $maglia->update(['stato_articolo' => ['it' => 'Nuova, mai indossata']]);
        $this->assertSame(self::STATO_IT, $riga->fresh()->statoArticoloIn('it'));
    }

    public function test_il_cliente_ritrova_lo_stato_nel_dettaglio_dell_ordine(): void
    {
        $user = User::factory()->create();
        $order = $this->ordina($this->magliaIndossata());
        $order->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)
            ->get(route('shop.order.show', $order->order_number))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.items.0.testo_stato_articolo', self::STATO_IT));
    }

    public function test_l_email_di_conferma_riporta_lo_stato(): void
    {
        $order = $this->ordina($this->magliaIndossata());

        $italiana = new OrderConfirmation($order->fresh());
        $italiana->assertSeeInHtml("Stato dell'articolo");
        $italiana->assertSeeInHtml('Segni di gioco, non lavata, autografo originale.');

        $order->forceFill(['locale' => 'en'])->save();
        $inglese = new OrderConfirmation($order->fresh());
        $inglese->assertSeeInHtml('Item condition');
        $inglese->assertSeeInHtml(self::STATO_EN);
    }

    public function test_il_pannello_ordini_mostra_lo_stato(): void
    {
        $order = $this->ordina($this->magliaIndossata());

        Livewire::actingAs($this->superAdmin())
            ->test(OrderItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditOrder::class])
            ->assertOk()
            ->assertSee('Segni di gioco, non lavata');
    }

    // --- Aiuti -------------------------------------------------------------

    private function prodotto(array $attributi = []): Product
    {
        return Product::factory()->create([
            'product_category_id' => ProductCategory::factory(),
            'is_active' => true,
            'type' => ProductType::Simple,
            'price' => 75,
            'stock' => 5,
            ...$attributi,
        ]);
    }

    private function magliaIndossata(array $attributi = []): Product
    {
        return $this->prodotto([
            'usato_o_autografato' => true,
            'stato_articolo' => ['it' => self::STATO_IT, 'en' => self::STATO_EN],
            ...$attributi,
        ]);
    }

    private function ordina(Product ...$prodotti): Order
    {
        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 7, 'free_threshold' => null]);

        $cart = Cart::create(['session_id' => 'sessione-stato', 'expires_at' => now()->addDay()]);
        foreach ($prodotti as $prodotto) {
            $cart->items()->create(['product_id' => $prodotto->id, 'quantity' => 1]);
        }

        $indirizzo = [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'street' => 'Via Roma 1',
            'city' => 'Firenze',
            'zip_code' => '50100',
            'province' => 'FI',
        ];

        return app(CheckoutService::class)->createOrder($cart->fresh(), [
            'guest_name' => 'Mario Rossi',
            'guest_email' => 'mario@example.com',
            'country' => 'IT',
            'shipping_address' => $indirizzo,
            'billing_address' => $indirizzo,
            'payment_gateway' => 'stripe',
            'privacy_accepted_at' => now(),
        ]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $user->refresh();
    }
}

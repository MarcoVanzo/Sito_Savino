<?php

namespace Tests\Feature\Shop;

use App\Enums\StockMovementType;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Overselling sotto concorrenza.
 *
 * Dopo l'audit lo stock non si tocca più nel webhook di pagamento: la riserva
 * avviene in CheckoutService::createOrder (transazione + SELECT ... FOR UPDATE)
 * e l'applicazione in StockMovementObserver (UPDATE condizionale atomico).
 * Nessun test copriva il caso in cui due clienti comprano l'ultimo pezzo nello
 * stesso istante.
 *
 * PHPUnit è a processo singolo, quindi non esistono due richieste davvero
 * parallele. La concorrenza qui è però reale dal punto di vista del database:
 * si aprono DUE connessioni MySQL distinte, ognuna con la propria transazione,
 * e si interlacciano a mano le operazioni (il primo checkout resta aperto
 * mentre parte il secondo). I lock di riga di InnoDB e l'UPDATE condizionale
 * vengono quindi esercitati davvero — è il motivo per cui il database di test
 * è MySQL e non SQLite.
 *
 * Perché niente transazione di test: RefreshDatabase avvolge ogni test in una
 * transazione, e ciò che una connessione non ha committato è invisibile
 * all'altra. Qui le scritture devono committare per davvero, quindi la lista
 * delle connessioni da avvolgere è vuota e la pulizia è manuale (tearDown).
 */
class CheckoutConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Nessuna connessione avvolta in transazione: i due checkout devono
     * vedersi a vicenda.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = [];

    /** Seconda connessione reale verso lo stesso database di test. */
    private const RIVAL = 'checkout_rival';

    /**
     * Tabelle da ripulire a mano, dalle figlie alle padri.
     *
     * @var array<int, string>
     */
    private const CLEANUP_TABLES = [
        'stock_movements',
        'order_items',
        'orders',
        'cart_items',
        'carts',
        'products',
        'shipping_zones',
        'users',
        'shop_events',
        'activity_logs',
    ];

    /** @var array<string, int> */
    private array $highWaterMarks = [];

    /** Connessione di default della suite (quella configurata in phpunit.xml). */
    private string $baseConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseConnection = config('database.default');

        config(['database.connections.'.self::RIVAL => config('database.connections.'.$this->baseConnection)]);
        DB::purge(self::RIVAL);

        // Senza questo, la connessione che trova il lock occupato aspetterebbe
        // i 50 secondi di default di innodb_lock_wait_timeout.
        DB::connection(self::RIVAL)->statement('SET SESSION innodb_lock_wait_timeout = 3');
        DB::connection($this->baseConnection)->statement('SET SESSION innodb_lock_wait_timeout = 3');

        foreach (self::CLEANUP_TABLES as $table) {
            $this->highWaterMarks[$table] = Schema::hasTable($table)
                ? (int) DB::table($table)->max('id')
                : 0;
        }
    }

    protected function tearDown(): void
    {
        // Le transazioni rimaste aperte da un test fallito bloccherebbero la
        // pulizia (e i test successivi) sui lock di riga.
        foreach ([self::RIVAL, $this->baseConnection] as $connection) {
            while (DB::connection($connection)->transactionLevel() > 0) {
                DB::connection($connection)->rollBack();
            }
        }

        DB::setDefaultConnection($this->baseConnection);

        foreach (self::CLEANUP_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('id', '>', $this->highWaterMarks[$table] ?? 0)->delete();
            }
        }

        DB::purge(self::RIVAL);

        parent::tearDown();
    }

    /**
     * Due clienti, un solo pezzo disponibile, transazioni sovrapposte.
     *
     * Fasi:
     *  1. il primo checkout gira su una connessione e resta aperto (non committa);
     *  2. il secondo checkout parte sull'altra connessione: deve trovare la
     *     riga bloccata e non riuscire a passare;
     *  3. il primo committa: lo stock è 0;
     *  4. il secondo riprova: viene respinto per stock insufficiente.
     *
     * Alla fine deve esistere UN solo ordine e lo stock non deve mai essere
     * andato sotto zero.
     */
    public function test_two_simultaneous_checkouts_on_the_last_unit_do_not_oversell(): void
    {
        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 7, 'free_threshold' => null]);
        $product = Product::factory()->create(['stock' => 1, 'price' => 40]);

        $firstCart = $this->cartWithOneUnitOf($product, 'sessione-primo');
        $secondCart = $this->cartWithOneUnitOf($product, 'sessione-secondo');

        // Fase 1 — il primo checkout entra in transazione e si ferma prima del
        // commit: è la finestra in cui, senza lock, il secondo leggerebbe
        // ancora "stock 1".
        DB::connection(self::RIVAL)->beginTransaction();
        $firstOrder = $this->runCheckoutOn(self::RIVAL, $firstCart->id, 'primo@example.test');

        // Fase 2 — il secondo checkout parte mentre il primo è ancora aperto.
        $blocked = $this->captureThrowable(
            fn () => $this->runCheckoutOn($this->baseConnection, $secondCart->id, 'secondo@example.test')
        );

        $this->assertInstanceOf(
            QueryException::class,
            $blocked,
            'Il secondo checkout è passato mentre il primo teneva il lock sulla riga del prodotto: i due possono riservare lo stesso pezzo.'
        );
        $this->assertStringContainsString(
            'Lock wait timeout',
            $blocked->getMessage(),
            'Il secondo checkout deve restare in attesa del lock del primo, non fallire per altro.'
        );

        // Fase 3 — il primo conclude.
        DB::connection(self::RIVAL)->commit();

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(0, (int) Product::find($product->id)->stock, 'Il primo checkout ha riservato il pezzo.');

        // Fase 4 — il secondo riprova a bocce ferme.
        $rejected = $this->captureThrowable(
            fn () => $this->runCheckoutOn($this->baseConnection, $secondCart->id, 'secondo@example.test')
        );

        $this->assertInstanceOf(
            ValidationException::class,
            $rejected,
            'Il secondo checkout deve essere respinto con un errore di validazione sullo stock, non creare un ordine né esplodere.'
        );
        $this->assertArrayHasKey('stock', $rejected->errors());

        $this->assertSame(
            0,
            (int) Product::find($product->id)->stock,
            'Lo stock non deve mai scendere sotto zero: sarebbe merce venduta due volte.'
        );
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, Order::whereKey($firstOrder->id)->count());
        $this->assertSame(
            -1,
            (int) StockMovement::where('product_id', $product->id)->sum('quantity'),
            'Un solo movimento di scarico: il secondo checkout non deve lasciare traccia.'
        );
    }

    /**
     * L'UPDATE condizionale dell'observer visto da vicino, senza concorrenza:
     * un movimento più grande dello stock disponibile non viene applicato e
     * l'eccezione fa rollback dell'intera transazione (movimento compreso).
     *
     * È il guard che regge il caso in cui la validazione a monte sia stata
     * aggirata: senza `where('stock', '>=', ...)` lo stock finirebbe a -1.
     */
    public function test_conditional_update_refuses_to_push_stock_below_zero(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $refused = $this->captureThrowable(fn () => DB::transaction(function () use ($product) {
            StockMovement::create([
                'product_id' => $product->id,
                'quantity' => -2,
                'type' => StockMovementType::Sale,
                'notes' => 'Scarico più grande della giacenza',
            ]);
        }));

        $this->assertInstanceOf(
            \RuntimeException::class,
            $refused,
            'Un movimento di -2 su una giacenza di 1 deve essere rifiutato.'
        );
        $this->assertStringContainsString('Stock insufficiente', $refused->getMessage());

        $this->assertSame(1, (int) Product::find($product->id)->stock, 'La giacenza non va toccata.');
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    /**
     * La finestra TOCTOU vera e propria, con due connessioni.
     *
     * Si legge la giacenza (1 pezzo, "c'è"), poi un'altra connessione committa
     * il proprio scarico, e solo dopo si applica il movimento. È esattamente
     * l'ordine di eventi che rende inutile qualunque controllo fatto in PHP
     * prima dell'UPDATE: la riga letta non vale più.
     *
     * Con l'UPDATE condizionale il secondo scarico viene respinto; con un
     * decremento secco la giacenza finirebbe a -1.
     */
    public function test_stock_movement_is_rejected_when_another_connection_took_the_last_unit(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        // Lettura "ottimista": in questo istante il pezzo risulta disponibile.
        $this->assertSame(1, (int) Product::find($product->id)->stock);

        // Nel frattempo, su un'altra connessione, qualcun altro se lo prende.
        $taken = DB::connection(self::RIVAL)
            ->update('update products set stock = stock - 1 where id = ? and stock >= 1', [$product->id]);
        $this->assertSame(1, $taken, "L'altra connessione deve aver preso l'ultimo pezzo.");

        // Ora si applica il movimento deciso sulla lettura ormai stantia.
        $refused = $this->captureThrowable(fn () => DB::transaction(function () use ($product) {
            StockMovement::create([
                'product_id' => $product->id,
                'quantity' => -1,
                'type' => StockMovementType::Sale,
                'notes' => 'Scarico deciso su una lettura non più valida',
            ]);
        }));

        $this->assertInstanceOf(
            \RuntimeException::class,
            $refused,
            'Lo scarico è stato applicato su una giacenza già azzerata da un altro: overselling.'
        );
        $this->assertStringContainsString('Stock insufficiente', $refused->getMessage());

        $this->assertSame(0, (int) Product::find($product->id)->stock);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    /**
     * Esegue un checkout completo sulla connessione indicata.
     *
     * I modelli Eloquent risolvono la connessione di default al momento della
     * query: cambiando il default e ricaricando il carrello, l'intero
     * CheckoutService (lock compresi) gira sulla connessione voluta.
     */
    private function runCheckoutOn(string $connection, int $cartId, string $email): Order
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        try {
            $cart = Cart::findOrFail($cartId);

            return (new CheckoutService(app(CartService::class)))
                ->createOrder($cart, $this->checkoutPayload($email));
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    /**
     * Esegue il callback e restituisce l'eccezione sollevata, null se passa.
     *
     * Serve a non mettere `fail()` dentro un try: l'AssertionFailedError di
     * PHPUnit estende RuntimeException e verrebbe intercettata dal catch,
     * trasformando un test che avrebbe dovuto fallire in uno che fallisce con
     * un messaggio incomprensibile (o peggio, che passa).
     */
    private function captureThrowable(callable $callback): ?\Throwable
    {
        try {
            $callback();

            return null;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    private function cartWithOneUnitOf(Product $product, string $sessionId): Cart
    {
        $cart = Cart::create([
            'session_id' => $sessionId,
            'expires_at' => now()->addDay(),
        ]);

        $cart->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
        ]);

        return $cart;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(string $email): array
    {
        $address = [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'street' => 'Via Roma 1',
            'city' => 'Firenze',
            'zip_code' => '50100',
            'province' => 'FI',
        ];

        return [
            'guest_name' => 'Mario Rossi',
            'guest_email' => $email,
            'country' => 'IT',
            'shipping_address' => $address,
            'billing_address' => $address,
            'payment_gateway' => 'stripe',
            'privacy_accepted_at' => now(),
        ];
    }
}

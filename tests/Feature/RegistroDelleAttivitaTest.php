<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Bid;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Payments\PayPalPaymentService;
use App\Services\RevocaDelRiconoscimentoDeiVolti;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Il registro delle attività serve a rivedere il lavoro della redazione nel
 * pannello. Non deve copiare i dati dei clienti (checkout, account, offerte)
 * e non deve crescere per sempre: la pulizia settimanale annullava da sola
 * perché il pianificatore la lanciava con un `--force` che il comando non
 * conosceva, e il `confirm()` senza terminale risponde no.
 */
class RegistroDelleAttivitaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::SuperAdmin])->save();

        return $admin;
    }

    private function riga(string $modello, int $id, ?int $autore, ?array $changes, ?string $etichetta = null): int
    {
        return (int) DB::table('activity_logs')->insertGetId([
            'user_id' => $autore,
            'action' => 'updated',
            'model_type' => $modello,
            'model_id' => $id,
            'model_label' => $etichetta,
            'changes' => $changes === null ? null : json_encode($changes),
            'ip_address' => '203.0.113.7',
            'user_agent' => 'Browser del cliente',
            'created_at' => now(),
        ]);
    }

    // ─── Pulizia periodica ────────────────────────────────────────

    public function test_dal_pianificatore_cancella_i_log_oltre_i_180_giorni(): void
    {
        $vecchio = $this->riga(Product::class, 1, null, null);
        $recente = $this->riga(Product::class, 2, null, null);
        DB::table('activity_logs')->where('id', $vecchio)->update(['created_at' => now()->subDays(181)]);
        DB::table('activity_logs')->where('id', $recente)->update(['created_at' => now()->subDays(179)]);

        // La stessa riga di comando che lancia il pianificatore.
        $evento = collect(app(Schedule::class)->events())
            ->first(fn (Event $e) => str_contains((string) $e->command, 'activity-log:prune'));
        $this->assertNotNull($evento);
        $this->assertStringContainsString('--force', (string) $evento->command);

        $this->artisan('activity-log:prune --days=180 --force')->assertSuccessful();

        $this->assertDatabaseMissing('activity_logs', ['id' => $vecchio]);
        $this->assertDatabaseHas('activity_logs', ['id' => $recente]);
    }

    public function test_la_revoca_dei_volti_non_scade_con_il_registro(): void
    {
        $revoca = $this->riga(Product::class, 1, null, null);
        DB::table('activity_logs')->where('id', $revoca)->update([
            'action' => RevocaDelRiconoscimentoDeiVolti::AZIONE,
            'created_at' => now()->subDays(400),
        ]);

        $this->artisan('activity-log:prune --days=180 --force')->assertSuccessful();

        $this->assertDatabaseHas('activity_logs', ['id' => $revoca]);
    }

    public function test_senza_force_chiede_conferma_e_rispondendo_no_non_cancella(): void
    {
        $vecchio = $this->riga(Product::class, 1, null, null);
        DB::table('activity_logs')->where('id', $vecchio)->update(['created_at' => now()->subDays(200)]);

        $this->artisan('activity-log:prune')
            ->expectsConfirmation('Cancellare 1 record di log più vecchi di 180 giorni?', 'no')
            ->assertSuccessful();

        $this->assertDatabaseHas('activity_logs', ['id' => $vecchio]);
    }

    // ─── Niente dati dei clienti ──────────────────────────────────

    public function test_un_ordine_dal_checkout_pubblico_non_lascia_righe(): void
    {
        ShippingZone::factory()->create(['countries' => ['IT'], 'flat_rate' => 5, 'free_threshold' => null]);
        $prodotto = Product::factory()->create(['price' => 30, 'stock' => 5]);
        $cliente = User::factory()->create();
        $cliente->forceFill(['role' => UserRole::Customer])->save();
        $carrello = Cart::factory()->create(['user_id' => $cliente->id]);
        CartItem::factory()->create(['cart_id' => $carrello->id, 'product_id' => $prodotto->id, 'quantity' => 1]);

        $this->mock(PayPalPaymentService::class)
            ->shouldReceive('createSession')
            ->andReturn('https://www.paypal.com/checkoutnow?token=TOKEN-TEST');

        DB::table('activity_logs')->delete();

        $this->actingAs($cliente)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('shop.checkout.store'), [
                'shipping_first_name' => 'Maria',
                'shipping_last_name' => 'Bianchi',
                'shipping_street' => 'Via Roma 1',
                'shipping_city' => 'Firenze',
                'shipping_zip_code' => '50100',
                'shipping_province' => 'FI',
                'country' => 'IT',
                'billing_same_as_shipping' => true,
                'payment_gateway' => 'paypal',
                'privacy_accepted' => true,
                'phone' => '3331234567',
                'codice_fiscale' => 'BNCMRA85M41D612X',
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_lo_stato_cambiato_da_un_admin_si_registra_senza_dati_personali(): void
    {
        $ordine = Order::factory()->create([
            'guest_email' => 'cliente@example.test',
            'guest_name' => 'Maria Bianchi',
            'guest_phone' => '3331234567',
            'codice_fiscale' => 'BNCMRA85M41D612X',
            'notes' => 'Citofono Bianchi',
        ]);
        DB::table('activity_logs')->delete();

        $admin = $this->admin();
        $this->actingAs($admin);
        $ordine->status = OrderStatus::Shipped;
        $ordine->tracking_number = 'TRK123';
        $ordine->guest_name = 'Maria Rossi';
        $ordine->save();

        $riga = ActivityLog::sole();
        $this->assertSame($admin->id, $riga->user_id);
        $this->assertSame($ordine->order_number, $riga->model_label);
        $this->assertSame('shipped', $riga->changes['new']['status']);
        $this->assertSame('TRK123', $riga->changes['new']['tracking_number']);

        $testo = json_encode($riga->changes);
        foreach (['guest_name', 'guest_email', 'Bianchi', 'Rossi', 'cliente@example.test', 'BNCMRA'] as $vietato) {
            $this->assertStringNotContainsString($vietato, $testo);
        }
    }

    public function test_l_admin_che_modifica_un_cliente_non_ne_copia_nome_ed_email(): void
    {
        $cliente = User::factory()->create(['name' => 'Maria Bianchi']);
        $cliente->forceFill(['role' => UserRole::Customer])->save();
        DB::table('activity_logs')->delete();

        $this->actingAs($this->admin());
        $cliente->forceFill(['is_active' => false, 'email' => 'nuova@example.test'])->save();

        $riga = ActivityLog::sole();
        $this->assertSame('Cliente #'.$cliente->id, $riga->model_label);
        $this->assertSame(['is_active'], array_keys($riga->changes['new']));
    }

    public function test_un_azione_del_cliente_su_un_altro_modello_resta_senza_autore_ne_ip(): void
    {
        $cliente = User::factory()->create();
        $cliente->forceFill(['role' => UserRole::Customer])->save();
        $prodotto = Product::factory()->create(['price' => 30]);
        DB::table('activity_logs')->delete();

        $this->actingAs($cliente);
        $prodotto->update(['price' => 31]);

        $riga = ActivityLog::sole();
        $this->assertNull($riga->user_id);
        $this->assertNull($riga->ip_address);
        $this->assertNull($riga->user_agent);
    }

    public function test_i_modelli_scritti_dai_clienti_non_passano_dal_registro(): void
    {
        foreach ([Cart::class, CartItem::class, OrderItem::class, Bid::class, CouponUsage::class] as $modello) {
            $this->assertFalse(
                method_exists($modello, 'bootLogsActivity'),
                "{$modello} non deve usare LogsActivity",
            );
        }
    }

    // ─── Pulizia dello storico ────────────────────────────────────

    public function test_la_migrazione_ripulisce_lo_storico(): void
    {
        $admin = $this->admin();
        $cliente = User::factory()->create();
        $cliente->forceFill(['role' => UserRole::Customer])->save();
        DB::table('activity_logs')->delete();

        $carrello = $this->riga('App\\Models\\Cart', 1, $cliente->id, ['new' => ['session_id' => 'x']]);
        $offerta = $this->riga('App\\Models\\Bid', 1, $admin->id, ['new' => ['amount' => 10]]);
        $checkout = $this->riga(Order::class, 1, null, ['new' => ['guest_email' => 'a@example.test']]);
        $profilo = $this->riga(User::class, $cliente->id, $cliente->id, ['new' => ['phone' => '333']]);
        $prodotto = $this->riga(Product::class, 1, null, ['new' => ['name' => 'Maglia']]);
        $scarico = $this->riga('App\\Models\\StockMovement', 1, $cliente->id, ['new' => ['quantity' => -1]]);
        $rettifica = $this->riga('App\\Models\\StockMovement', 2, $admin->id, ['new' => ['quantity' => 5]]);
        $astaDaOfferta = $this->riga('App\\Models\\Auction', 1, $cliente->id, ['new' => ['current_price' => 120]]);

        $statoDaAdmin = $this->riga(Order::class, 2, $admin->id, [
            'old' => ['status' => 'paid', 'guest_name' => 'Maria Bianchi'],
            'new' => ['status' => 'shipped', 'guest_name' => 'Maria Rossi', 'shipping_address' => ['city' => 'Firenze']],
        ]);
        $soloRecapiti = $this->riga(Order::class, 3, $admin->id, ['old' => ['notes' => 'a'], 'new' => ['notes' => 'b']]);
        $clienteDaAdmin = $this->riga(User::class, $cliente->id, $admin->id, [
            'old' => ['is_active' => true, 'email' => 'vecchia@example.test'],
            'new' => ['is_active' => false, 'email' => 'nuova@example.test'],
        ], 'Maria Bianchi');
        $staffDaAdmin = $this->riga(User::class, $admin->id, $admin->id, ['new' => ['role' => 'super_admin', 'name' => 'Admin']], 'Admin');

        $migrazione = require database_path('migrations/2026_10_02_130000_il_registro_non_copia_i_dati_dei_clienti.php');
        $migrazione->up();

        foreach ([$carrello, $offerta, $checkout, $profilo, $scarico] as $tolta) {
            $this->assertDatabaseMissing('activity_logs', ['id' => $tolta]);
        }

        // Le righe di sistema e quelle fatte dai clienti su altri modelli
        // restano, senza autore, IP e browser; quelle dello staff li tengono.
        foreach ([$prodotto, $astaDaOfferta] as $anonima) {
            $this->assertDatabaseHas('activity_logs', ['id' => $anonima, 'user_id' => null, 'ip_address' => null, 'user_agent' => null]);
        }
        $this->assertDatabaseHas('activity_logs', ['id' => $rettifica, 'user_id' => $admin->id, 'ip_address' => '203.0.113.7']);

        $this->assertEquals(
            ['old' => ['status' => 'paid'], 'new' => ['status' => 'shipped']],
            ActivityLog::find($statoDaAdmin)->changes,
        );
        $this->assertNull(ActivityLog::find($soloRecapiti)->changes);

        $riga = ActivityLog::find($clienteDaAdmin);
        $this->assertEquals(['old' => ['is_active' => true], 'new' => ['is_active' => false]], $riga->changes);
        $this->assertSame('Cliente #'.$cliente->id, $riga->model_label);

        $riga = ActivityLog::find($staffDaAdmin);
        $this->assertEquals(['new' => ['role' => 'super_admin']], $riga->changes);
        $this->assertSame('Admin', $riga->model_label);

        // Rilanciata non cambia nulla.
        $prima = DB::table('activity_logs')->orderBy('id')->get()->toJson();
        $migrazione->up();
        $this->assertSame($prima, DB::table('activity_logs')->orderBy('id')->get()->toJson());
    }
}

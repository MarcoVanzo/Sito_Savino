<?php

namespace Tests\Feature\Lvf;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Lvf\LvfSyncHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La sincronizzazione gira ogni ora: se il sito della Lega cade, l'unico segnale
 * era una riga di log che nessuno legge. Qui si verifica che gli amministratori
 * vengano avvisati, ma solo quando il guasto è persistente.
 */
class LvfSyncHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $shopManager;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.lvf.base_url', 'https://www.legavolleyfemminile.it');
        config()->set('services.lvf.failure_alert_threshold', 3);
        config()->set('services.lvf.failure_alert_repeat_every', 24);

        $this->superAdmin = $this->userWithRole(UserRole::SuperAdmin);
        $this->shopManager = $this->userWithRole(UserRole::ShopManager);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role->value])->save();

        return $user;
    }

    /**
     * Il sito della Lega non risponde: ogni giro del comando fallisce.
     */
    private function fakeSiteDown(): void
    {
        Http::swap(new Factory);
        Http::fake(['*legavolleyfemminile.it/*' => Http::response('', 503)]);
    }

    private function fakeSiteUp(): void
    {
        Http::swap(new Factory);
        Http::fake([
            // Pagine valide ma senza gare né classifica: la sincronizzazione
            // non ha nulla da importare e termina con successo.
            '*legavolleyfemminile.it/*' => Http::response('<html><body><p>Nessuna gara</p></body></html>'),
        ]);
    }

    private function runSync(): int
    {
        return $this->artisan('lvf:sync', ['--season' => 2026, '--skip-stats' => true])->run();
    }

    /**
     * Conta i soli avvisi di sincronizzazione: la registrazione di un utente ne
     * genera altri («Nuovo utente in attesa»), che qui sono rumore.
     */
    private function alerts(User $user): int
    {
        return $user->notifications()
            ->where('data', 'like', '%Sincronizzazione Lega non riuscita%')
            ->count();
    }

    #[Test]
    public function un_fallimento_isolato_non_avvisa_nessuno(): void
    {
        // La Lega ha cali temporanei: un avviso a ogni singhiozzo verrebbe
        // ignorato proprio quando conta.
        $this->fakeSiteDown();

        $this->assertSame(1, $this->runSync());

        $this->assertSame(0, $this->alerts($this->superAdmin));
        $this->assertSame(1, app(LvfSyncHealth::class)->consecutiveFailures());
    }

    #[Test]
    public function dopo_tre_fallimenti_consecutivi_gli_amministratori_vengono_avvisati(): void
    {
        $this->fakeSiteDown();

        $this->runSync();
        $this->runSync();
        $this->assertSame(0, $this->alerts($this->superAdmin));

        $this->runSync();

        $this->assertSame(1, $this->alerts($this->superAdmin));

        $body = $this->superAdmin->notifications()
            ->where('data', 'like', '%Sincronizzazione Lega non riuscita%')
            ->first()->data['body'] ?? '';
        $this->assertStringContainsString('3 tentativi consecutivi', $body);
    }

    #[Test]
    public function lavviso_va_solo_ai_super_admin(): void
    {
        // È un guasto di integrazione, non una questione di shop.
        $this->fakeSiteDown();

        $this->runSync();
        $this->runSync();
        $this->runSync();

        $this->assertSame(1, $this->alerts($this->superAdmin));
        $this->assertSame(0, $this->alerts($this->shopManager));
    }

    #[Test]
    public function una_sincronizzazione_riuscita_azzera_la_serie(): void
    {
        // Due cali sparsi nel tempo non devono sommarsi fino alla soglia.
        $this->fakeSiteDown();
        $this->runSync();
        $this->runSync();

        $this->fakeSiteUp();
        $this->assertSame(0, $this->runSync());
        $this->assertSame(0, app(LvfSyncHealth::class)->consecutiveFailures());

        $this->fakeSiteDown();
        $this->runSync();
        $this->runSync();

        $this->assertSame(0, $this->alerts($this->superAdmin));
    }

    #[Test]
    public function lavviso_non_si_ripete_a_ogni_fallimento_successivo(): void
    {
        // Finché il guasto dura il comando fallisce ogni ora: senza freno
        // sarebbe una notifica all'ora.
        config()->set('services.lvf.failure_alert_threshold', 2);
        config()->set('services.lvf.failure_alert_repeat_every', 3);

        $this->fakeSiteDown();

        // Fallimenti 1..6: avviso al secondo (soglia) e al quinto (soglia + 3).
        for ($i = 0; $i < 6; $i++) {
            $this->runSync();
        }

        $this->assertSame(6, app(LvfSyncHealth::class)->consecutiveFailures());
        $this->assertSame(2, $this->alerts($this->superAdmin));
    }

    #[Test]
    public function il_contatore_sopravvive_alla_pulizia_della_cache(): void
    {
        // In produzione `start.sh` esegue `cache:clear` a ogni deploy: se il
        // contatore vivesse in cache un guasto lungo non arriverebbe mai a
        // soglia.
        $this->fakeSiteDown();
        $this->runSync();
        $this->runSync();

        Cache::flush();

        $this->assertSame(2, app(LvfSyncHealth::class)->consecutiveFailures());
        $this->assertDatabaseHas('site_settings', ['key' => LvfSyncHealth::FAILURES_KEY, 'value' => '2']);

        $this->runSync();
        $this->assertSame(1, $this->alerts($this->superAdmin));
    }
}

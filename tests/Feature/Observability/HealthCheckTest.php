<?php

namespace Tests\Feature\Observability;

use App\Enums\UserRole;
use App\Exceptions\UnhealthyApplicationException;
use App\Listeners\VerifyApplicationHealth;
use App\Models\User;
use App\Support\SchedulerHeartbeat;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `/up` è ciò su cui App Platform decide se riavviare l'istanza. Prima
 * rispondeva sempre 200 finché Apache era vivo, il che rendeva il controllo
 * inutile: il container restava "healthy" con il database irraggiungibile.
 *
 * Questi test bloccano i due errori opposti — un controllo che non fallisce mai
 * (inutile) e un controllo che fallisce dove il pianificatore non gira, cioè su
 * ogni macchina di sviluppo (rumore che si impara a ignorare).
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function risponde_ok_quando_tutto_funziona(): void
    {
        $this->get('/up')->assertOk();
    }

    #[Test]
    public function non_richiede_la_basic_auth_di_preview(): void
    {
        // Se PreviewBasicAuth coprisse `/up`, DigitalOcean riceverebbe 401 e
        // riavvierebbe il container all'infinito durante la fase di preview.
        config(['services.preview.user' => 'anteprima', 'services.preview.pass' => 'segreto']);

        $this->get('/up')->assertOk();
    }

    #[Test]
    public function in_sviluppo_non_pretende_il_pianificatore(): void
    {
        // In locale e in CI lo scheduler non gira: senza questa esenzione `/up`
        // fallirebbe su ogni macchina di sviluppo.
        Cache::forget(SchedulerHeartbeat::CACHE_KEY);

        $this->get('/up')->assertOk();
    }

    /**
     * Il caso che conta di più, e che una versione precedente sbagliava.
     *
     * `/up` decide se App Platform riavvia il container WEB. Il pianificatore
     * ha un componente suo: riavviare il web non lo resuscita. Far fallire il
     * controllo metterebbe il sito in ciclo di riavvio — offline — per un
     * guasto che senza health check sarebbe stato soltanto silenzioso.
     */
    #[Test]
    public function un_pianificatore_fermo_non_butta_giu_il_sito(): void
    {
        $this->app['env'] = 'production';
        Cache::forget(SchedulerHeartbeat::CACHE_KEY);

        $this->get('/up')->assertOk();
    }

    #[Test]
    public function un_pianificatore_fermo_avvisa_i_super_admin(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::SuperAdmin->value])->save();

        $this->app['env'] = 'production';
        Cache::forget(SchedulerHeartbeat::CACHE_KEY);

        $this->get('/up')->assertOk();

        $this->assertSame(
            1,
            $admin->notifications()->whereJsonContains('data->title', 'Il pianificatore si è fermato')->count(),
        );
    }

    #[Test]
    public function l_avviso_sul_pianificatore_e_silenziato(): void
    {
        // App Platform interroga `/up` ogni 30 secondi: senza silenziatore un
        // pianificatore morto produrrebbe 2.880 segnalazioni al giorno.
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::SuperAdmin->value])->save();

        $this->app['env'] = 'production';
        Cache::forget(SchedulerHeartbeat::CACHE_KEY);

        $this->get('/up');
        $this->get('/up');
        $this->get('/up');

        $this->assertSame(
            1,
            $admin->notifications()->whereJsonContains('data->title', 'Il pianificatore si è fermato')->count(),
        );
    }

    #[Test]
    public function con_un_battito_recente_non_avvisa_nessuno(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => UserRole::SuperAdmin->value])->save();

        $this->app['env'] = 'production';
        SchedulerHeartbeat::beat();

        $this->get('/up')->assertOk();

        $this->assertSame(0, $admin->notifications()->count());
    }

    /**
     * Il rovescio della medaglia: ciò che un riavvio PUÒ risolvere deve
     * continuare a far fallire il controllo, altrimenti l'health check torna a
     * essere quello che era — una porta TCP aperta.
     *
     * Si esercita il listener direttamente invece di passare da `/up`: rendere
     * irraggiungibile la connessione romperebbe la transazione di
     * RefreshDatabase, e il test fallirebbe nel teardown invece che
     * sull'asserzione.
     */
    #[Test]
    public function un_database_irraggiungibile_fa_fallire_il_controllo(): void
    {
        DB::shouldReceive('connection')->andThrow(new \RuntimeException('Connection refused'));

        $this->expectException(UnhealthyApplicationException::class);
        $this->expectExceptionMessage('database');

        app(VerifyApplicationHealth::class)->handle(new DiagnosingHealth);
    }

    #[Test]
    public function una_cache_non_rileggibile_fa_fallire_il_controllo(): void
    {
        // Scrittura che passa ma rilettura che non restituisce il valore: è il
        // guasto silenzioso di uno store rotto, e un riavvio può risolverlo.
        Cache::shouldReceive('put')->andReturn(true);
        Cache::shouldReceive('get')->andReturn(null);

        $this->expectException(UnhealthyApplicationException::class);
        $this->expectExceptionMessage('cache');

        app(VerifyApplicationHealth::class)->handle(new DiagnosingHealth);
    }
}

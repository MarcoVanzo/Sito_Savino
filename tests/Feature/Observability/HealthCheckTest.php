<?php

namespace Tests\Feature\Observability;

use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Cache;
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

    #[Test]
    public function in_produzione_fallisce_se_il_pianificatore_non_e_mai_partito(): void
    {
        $this->app['env'] = 'production';
        Cache::forget(SchedulerHeartbeat::CACHE_KEY);

        $this->get('/up')->assertStatus(500);
    }

    #[Test]
    public function in_produzione_fallisce_se_il_battito_e_vecchio(): void
    {
        $this->app['env'] = 'production';
        Cache::put(
            SchedulerHeartbeat::CACHE_KEY,
            now()->subSeconds(SchedulerHeartbeat::STALE_AFTER_SECONDS + 60)->timestamp,
            now()->addHour(),
        );

        $this->get('/up')->assertStatus(500);
    }

    #[Test]
    public function in_produzione_passa_con_un_battito_recente(): void
    {
        $this->app['env'] = 'production';
        SchedulerHeartbeat::beat();

        $this->get('/up')->assertOk();
    }
}

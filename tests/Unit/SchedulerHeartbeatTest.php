<?php

namespace Tests\Unit;

use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchedulerHeartbeatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(SchedulerHeartbeat::CACHE_KEY);
    }

    #[Test]
    public function senza_battito_risulta_fermo(): void
    {
        $this->assertNull(SchedulerHeartbeat::secondsSinceLastBeat());
        $this->assertTrue(SchedulerHeartbeat::isStale());
    }

    #[Test]
    public function un_battito_appena_registrato_non_e_fermo(): void
    {
        SchedulerHeartbeat::beat();

        $this->assertSame(0, SchedulerHeartbeat::secondsSinceLastBeat());
        $this->assertFalse(SchedulerHeartbeat::isStale());
    }

    #[Test]
    public function oltre_la_soglia_risulta_fermo(): void
    {
        Cache::put(
            SchedulerHeartbeat::CACHE_KEY,
            now()->subSeconds(SchedulerHeartbeat::STALE_AFTER_SECONDS + 1)->timestamp,
            now()->addHour(),
        );

        $this->assertTrue(SchedulerHeartbeat::isStale());
    }

    #[Test]
    public function esattamente_sulla_soglia_non_e_ancora_fermo(): void
    {
        Cache::put(
            SchedulerHeartbeat::CACHE_KEY,
            now()->subSeconds(SchedulerHeartbeat::STALE_AFTER_SECONDS)->timestamp,
            now()->addHour(),
        );

        $this->assertFalse(SchedulerHeartbeat::isStale());
    }

    /**
     * Un valore illeggibile non deve passare per "battito valido": è la
     * differenza fra accorgersi di un guasto e dichiararlo risolto.
     */
    #[Test]
    public function un_valore_non_numerico_vale_come_assente(): void
    {
        Cache::put(SchedulerHeartbeat::CACHE_KEY, 'non-un-timestamp', now()->addHour());

        $this->assertNull(SchedulerHeartbeat::secondsSinceLastBeat());
        $this->assertTrue(SchedulerHeartbeat::isStale());
    }

    #[Test]
    public function il_comando_registra_il_battito(): void
    {
        $this->artisan('scheduler:beat')->assertSuccessful();

        $this->assertFalse(SchedulerHeartbeat::isStale());
    }
}

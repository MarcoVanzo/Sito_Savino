<?php

namespace App\Listeners;

use App\Exceptions\UnhealthyApplicationException;
use App\Support\SchedulerHeartbeat;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verifiche eseguite da `/up`, l'endpoint che App Platform interroga.
 *
 * Prima esisteva solo il controllo TCP di default sulla porta: Apache vivo
 * significava container sano. Bastava che il database fosse irraggiungibile o
 * che il pianificatore fosse morto perché il sito restasse "healthy" mentre non
 * serviva più nulla di utile.
 *
 * Ogni verifica solleva se fallisce, così `/up` risponde 500 e DigitalOcean
 * riavvia l'istanza. Il messaggio finisce anche su Sentry: il riavvio da solo
 * nasconderebbe la causa.
 */
class VerifyApplicationHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        $this->verifyDatabase();
        $this->verifyCache();
        $this->verifyScheduler();
    }

    private function verifyDatabase(): void
    {
        try {
            DB::connection()->getPdo()->query('SELECT 1');
        } catch (Throwable $e) {
            throw UnhealthyApplicationException::for('database', $e->getMessage());
        }
    }

    /**
     * La cache non è un dettaglio di performance: in produzione regge sessioni,
     * code e i lock `withoutOverlapping()` dei comandi schedulati. Se cade, il
     * sito è in piedi ma non tiene più un carrello né un login.
     */
    private function verifyCache(): void
    {
        try {
            $canary = 'health:cache:'.now()->timestamp;
            Cache::put($canary, true, 10);

            if (Cache::get($canary) !== true) {
                throw UnhealthyApplicationException::for('cache', 'scrittura non rileggibile');
            }

            Cache::forget($canary);
        } catch (UnhealthyApplicationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw UnhealthyApplicationException::for('cache', $e->getMessage());
        }
    }

    /**
     * Solo in produzione: in sviluppo e nei test il pianificatore non gira, e
     * far fallire `/up` su ogni macchina di sviluppo trasformerebbe il controllo
     * in rumore da ignorare — che è il modo in cui i controlli smettono di servire.
     */
    private function verifyScheduler(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (! SchedulerHeartbeat::isStale()) {
            return;
        }

        $elapsed = SchedulerHeartbeat::secondsSinceLastBeat();

        throw UnhealthyApplicationException::for(
            'scheduler',
            $elapsed === null
                ? 'nessun battito registrato: il pianificatore non è mai partito'
                : "ultimo battito {$elapsed}s fa, soglia ".SchedulerHeartbeat::STALE_AFTER_SECONDS.'s',
        );
    }
}

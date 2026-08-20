<?php

namespace App\Listeners;

use App\Exceptions\UnhealthyApplicationException;
use App\Services\AdminNotificationService;
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
 * Database e cache sollevano se falliscono: `/up` risponde 500 e DigitalOcean
 * riavvia l'istanza, che è la cura giusta per un container in stato incoerente.
 * Il messaggio finisce anche su Sentry, perché il riavvio da solo nasconderebbe
 * la causa.
 *
 * Il pianificatore invece segnala soltanto — vedi reportSchedulerIfStale().
 */
class VerifyApplicationHealth
{
    /**
     * Da quanto lo stallo deve durare prima di essere segnalato.
     *
     * Il battito vive nella cache, e `start.sh` esegue `cache:clear` a ogni
     * avvio del container web: appena dopo un rilascio il battito risulta
     * assente anche con il pianificatore vivo, e senza questa finestra ogni
     * riavvio del web produceva un falso «non è mai partito». Il battito è al
     * minuto, quindi due minuti bastano a distinguere un buco di cache da un
     * processo morto.
     */
    private const STALE_CONFIRM_SECONDS = 120;

    /**
     * Momento della prima osservazione di stallo della serie in corso.
     */
    private const STALE_SINCE_KEY = 'scheduler:stale-since';

    public function handle(DiagnosingHealth $event): void
    {
        // Solo ciò che un riavvio del container può rimettere a posto fa
        // fallire il controllo. Il resto segnala e lascia il sito in piedi.
        $this->verifyDatabase();
        $this->verifyCache();

        $this->reportSchedulerIfStale();
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
     * Il pianificatore fermo SEGNALA ma non fa fallire il controllo.
     *
     * È una distinzione che costa cara sbagliare. `/up` decide se App Platform
     * riavvia il container web; ha senso far fallire solo ciò che un riavvio
     * del web può davvero rimettere a posto. Il pianificatore ha un componente
     * suo: riavviare il web non lo resuscita, quindi far fallire qui
     * significherebbe mettere il sito in ciclo di riavvio — offline — per un
     * guasto che senza health check sarebbe stato soltanto silenzioso.
     *
     * Prima che il pianificatore uscisse dal container web il ragionamento
     * opposto era corretto, ed è così che questo controllo è nato.
     *
     * Solo in produzione: in sviluppo il pianificatore non gira, e un avviso su
     * ogni macchina di sviluppo diventa rumore da ignorare.
     */
    private function reportSchedulerIfStale(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (! SchedulerHeartbeat::isStale()) {
            Cache::forget(self::STALE_SINCE_KEY);

            return;
        }

        if (! $this->staleLongEnoughToBeReal()) {
            return;
        }

        // App Platform interroga `/up` ogni 30 secondi: senza silenziatore un
        // pianificatore morto produrrebbe 2.880 segnalazioni al giorno.
        // `add()` è atomico, quindi regge anche con più istanze del web.
        if (! Cache::add('scheduler-stale-alert', true, 3600)) {
            return;
        }

        $elapsed = SchedulerHeartbeat::secondsSinceLastBeat();

        report(UnhealthyApplicationException::for(
            'scheduler',
            $elapsed === null
                ? 'nessun battito registrato: il pianificatore non è mai partito'
                : "ultimo battito {$elapsed}s fa, soglia ".SchedulerHeartbeat::STALE_AFTER_SECONDS.'s',
        ));

        app(AdminNotificationService::class)->notifySchedulerStalled($elapsed);
    }

    /**
     * Vero se lo stallo è stato osservato anche STALE_CONFIRM_SECONDS fa.
     *
     * La prima osservazione non avvisa: registra soltanto l'istante e lascia
     * al pianificatore il tempo del battito successivo. La chiave scade dopo
     * un'ora perché un battito tornato normale la cancella
     * (reportSchedulerIfStale), e la TTL serve solo come rete di sicurezza.
     */
    private function staleLongEnoughToBeReal(): bool
    {
        $since = Cache::get(self::STALE_SINCE_KEY);

        if (! is_numeric($since)) {
            Cache::put(self::STALE_SINCE_KEY, now()->timestamp, now()->addHour());

            return false;
        }

        return now()->timestamp - (int) $since >= self::STALE_CONFIRM_SECONDS;
    }
}

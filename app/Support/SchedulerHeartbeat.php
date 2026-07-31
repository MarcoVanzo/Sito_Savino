<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Battito del pianificatore.
 *
 * Lo scheduler è il processo che chiude le aste, sblocca lo stock degli ordini
 * abbandonati e sincronizza i dati della Lega. Se muore, tutto il resto continua
 * a funzionare: il sito risponde, il pannello si apre, nessuno se ne accorge —
 * finché un'asta non si chiude o uno stock resta bloccato per giorni.
 *
 * Il battito rende il guasto visibile: lo scheduler scrive un timestamp ogni
 * minuto e l'health check verifica che non sia troppo vecchio. Il container web
 * e quello dello scheduler sono processi distinti, quindi il valore deve stare
 * su uno store condiviso — in produzione la cache ha driver `database`.
 */
class SchedulerHeartbeat
{
    public const CACHE_KEY = 'scheduler:heartbeat';

    /**
     * Oltre questa soglia il pianificatore è considerato fermo.
     *
     * Il battito è al minuto: cinque minuti lasciano margine per un comando
     * lento che occupa il ciclo, senza aspettare mezz'ora prima di accorgersi
     * che il processo è morto.
     */
    public const STALE_AFTER_SECONDS = 300;

    /**
     * Registra il passaggio del pianificatore.
     *
     * La TTL è volutamente più lunga della soglia: serve a distinguere
     * "battito vecchio" (lo scheduler era vivo e si è fermato) da "chiave
     * assente" (non è mai partito). Se la voce scadesse insieme alla soglia,
     * i due casi diventerebbero indistinguibili.
     */
    public static function beat(): void
    {
        Cache::put(self::CACHE_KEY, now()->timestamp, now()->addHour());
    }

    /**
     * Secondi trascorsi dall'ultimo battito, o null se non è mai arrivato.
     */
    public static function secondsSinceLastBeat(): ?int
    {
        $last = Cache::get(self::CACHE_KEY);

        if (! is_numeric($last)) {
            return null;
        }

        return max(0, now()->timestamp - (int) $last);
    }

    public static function isStale(): bool
    {
        $elapsed = self::secondsSinceLastBeat();

        return $elapsed === null || $elapsed > self::STALE_AFTER_SECONDS;
    }
}

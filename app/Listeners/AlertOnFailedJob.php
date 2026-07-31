<?php

namespace App\Listeners;

use App\Services\AdminNotificationService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Avvisa quando un job in coda esaurisce i tentativi.
 *
 * Un job fallito finisce in `failed_jobs` e lì resta: nessuno apre quella
 * tabella. Fra i job in coda ci sono le conferme d'ordine e le email ai
 * vincitori d'asta, quindi il fallimento silenzioso si manifesta come un
 * cliente che non riceve nulla e scrive per sapere perché.
 *
 * L'eccezione arriva già a Sentry tramite l'integrazione con le code: qui si
 * aggiunge la notifica nel pannello, che è dove Marco guarda per primo.
 */
class AlertOnFailedJob
{
    /**
     * Un solo avviso all'ora per tipo di job.
     *
     * Quando una coda si rompe, si rompe per tutti i job dello stesso tipo: un
     * gateway di posta irraggiungibile può produrre decine di fallimenti
     * identici in pochi minuti, e altrettante notifiche renderebbero il
     * pannello inutilizzabile proprio mentre serve.
     */
    private const THROTTLE_SECONDS = 3600;

    public function __construct(
        private readonly AdminNotificationService $notifications,
    ) {}

    public function handle(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();

        // Il nome della classe si normalizza, non si sottopone a hash: una
        // chiave leggibile si ritrova ispezionando la cache durante un guasto,
        // e non c'è niente da nascondere in un nome di classe. (Con md5 qui
        // Sonar segnalava un algoritmo debole — giustamente, perché da fuori
        // non si distingue un hash usato per chiavi da uno usato per sicurezza.)
        $key = 'job-failed-alert:'.Str::slug(str_replace('\\', '-', $jobName));

        // `add()` è atomico: se la chiave esiste già ritorna false senza
        // sovrascriverla. Con get()+put() due worker paralleli passerebbero
        // entrambi il controllo prima che l'altro scriva.
        $isFirstOfWindow = Cache::add($key, true, self::THROTTLE_SECONDS);

        if (! $isFirstOfWindow) {
            return;
        }

        $this->notifications->notifyJobFailed($jobName, $event->exception->getMessage());
    }
}

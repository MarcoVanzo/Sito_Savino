<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Statistiche giocatrici: il comando genera dati SIMULATI e si rifiuta di girare
// in produzione. La Lega non pubblica le statistiche individuali in una forma
// estraibile, quindi resta un aiuto per popolare gli ambienti di sviluppo.
if (! app()->isProduction()) {
    Schedule::command('sync:legavolley')->daily()->withoutOverlapping();
}

// Battito del pianificatore, letto dall'health check `/up`. Se questo processo
// muore il sito continua a rispondere e nessuno se ne accorge: le aste non si
// chiudono, lo stock degli ordini abbandonati resta bloccato, la Lega non si
// sincronizza. Deve restare il primo comando del ciclo e non avere dipendenze.
Schedule::command('scheduler:beat')->everyMinute();

// Calendario, risultati e classifica dal sito della Lega. Ogni ora: i referti
// arrivano a fine gara e la classifica si aggiorna subito dopo. I fallimenti
// sono contati da LvfSyncHealth, che avvisa i Super Admin quando il guasto
// dura (soglia in `services.lvf.failure_alert_threshold`).
Schedule::command('lvf:sync')->hourly()->withoutOverlapping();

// Da qui in giù tutto ha withoutOverlapping(). Non è una precauzione contro la
// lentezza dei singoli comandi — la sitemap e le potature girano una volta al
// giorno — ma contro l'esecuzione doppia: il lock è condiviso via cache, quindi
// vale anche fra istanze diverse. Oggi il componente `scheduler` ha
// instance_count 1 e il caso non si presenta; senza il lock, alzarlo a 2 farebbe
// potare i log e rigenerare la sitemap due volte in parallelo, e chi lo alzasse
// non avrebbe modo di accorgersene.
Schedule::command('sitemap:generate')->daily()->at('04:00')->withoutOverlapping();

// I file appena caricati arrivano su Spaces senza Content-Type e senza
// Cache-Control (vedi FixRemoteMediaMetadata): si ripassano quelli recenti,
// così le immagini nuove nascono cacheabili senza interventi manuali.
Schedule::command('media:fix-remote-metadata --since="3 days ago"')->dailyAt('04:30')->withoutOverlapping();

// Insight Instagram: la Graph API non dà lo storico giorno per giorno, quindi
// ogni giornata costa una chiamata. Di notte se ne recuperano fino a 120, così
// aprendo la pagina il grafico è già pieno e restano da scaricare solo i giorni
// nuovi. L'ora è tarda di proposito: Meta consolida i dati con un paio di
// giorni di ritardo, non c'è nessun vantaggio ad arrivare per primi.
Schedule::command('social:sync-meta --days=90')->dailyAt('03:30')->withoutOverlapping();

// Traffico dei siti: la serie si salva già a ogni apertura del pannello, ma se
// per un mese nessuno lo apre quel mese non entra in archivio e i confronti
// anno su anno restano bucati.
Schedule::command('analytics:sync-ga4 --days=90')->dailyAt('05:00')->withoutOverlapping();

// Pulizia periodica
Schedule::command('activity-log:prune --days=180 --force')->weekly()->withoutOverlapping();
Schedule::command('model:prune')->daily()->withoutOverlapping();
Schedule::command('carts:prune-expired')->daily()->at('03:00')->withoutOverlapping();

// Controllo ordini non pagati: cancella Stripe/PayPal abbandonati (1h) e bonifici scaduti (7gg)
// Frequenza alta per rilasciare stock bloccato da checkout abbandonati il prima possibile
Schedule::command('order:check-unpaid')->everyTenMinutes()->withoutOverlapping();

// Aste: attivazione aste programmate (ogni minuto)
Schedule::command('auction:activate')->everyMinute()->withoutOverlapping();

// Aste: chiusura aste scadute (ogni minuto)
Schedule::command('auction:close')->everyMinute()->withoutOverlapping();

// Aste: verifica pagamenti vincitori (ogni ora)
Schedule::command('auction:check-payments')->hourly()->withoutOverlapping();

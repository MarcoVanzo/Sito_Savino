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
    Schedule::command('sync:legavolley')->daily();
}

// Calendario, risultati e classifica dal sito della Lega. Ogni ora: i referti
// arrivano a fine gara e la classifica si aggiorna subito dopo. I fallimenti
// sono contati da LvfSyncHealth, che avvisa i Super Admin quando il guasto
// dura (soglia in `services.lvf.failure_alert_threshold`).
Schedule::command('lvf:sync')->hourly()->withoutOverlapping();

Schedule::command('sitemap:generate')->daily()->at('04:00');

// Pulizia periodica
Schedule::command('activity-log:prune --days=180 --force')->weekly();
Schedule::command('model:prune')->daily();
Schedule::command('carts:prune-expired')->daily()->at('03:00');

// Controllo ordini non pagati: cancella Stripe/PayPal abbandonati (1h) e bonifici scaduti (7gg)
// Frequenza alta per rilasciare stock bloccato da checkout abbandonati il prima possibile
Schedule::command('order:check-unpaid')->everyTenMinutes()->withoutOverlapping();

// Aste: attivazione aste programmate (ogni minuto)
Schedule::command('auction:activate')->everyMinute()->withoutOverlapping();

// Aste: chiusura aste scadute (ogni minuto)
Schedule::command('auction:close')->everyMinute()->withoutOverlapping();

// Aste: verifica pagamenti vincitori (ogni ora)
Schedule::command('auction:check-payments')->hourly()->withoutOverlapping();

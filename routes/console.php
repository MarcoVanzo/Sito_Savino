<?php

use App\Jobs\RicostruisciLaCacheDellaGallery;
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

// I comunicati che la redazione continua a pubblicare sul vecchio sito, finche'
// il dominio e' suo. Ogni ora e non una volta al giorno perche' la finestra e'
// di pochi giorni: un comunicato uscito in mattinata e il passaggio del dominio
// nel pomeriggio starebbero nello stesso giorno, e quel comunicato si
// perderebbe per sempre — staccato il vecchio sito, non e' piu' interrogabile.
// Quando non c'e' niente di nuovo il giro e' una sola richiesta.
//
// Si spegne da solo il giorno dopo il passaggio
// (`services.vecchio_sito.leggibile_fino_a`): da li' `savinodelbenevolley.it` e'
// questo sito, `wp-json` risponde 404 e il comando fallirebbe a ogni giro. Il
// comando resta lanciabile a mano, per l'ultimo giro prima dello switch.
Schedule::command('news:importa-dal-vecchio-sito')
    ->hourly()
    ->withoutOverlapping()
    ->skip(fn (): bool => now()->greaterThanOrEqualTo((string) config('services.vecchio_sito.leggibile_fino_a')));

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

// L'archivio della gallery in cache dura un giorno e si rigenera in coda a
// ogni modifica; il giro orario copre il caso in cui un job sia andato perso e
// tiene la copia fresca anche quando nessuno tocca niente.
Schedule::job(new RicostruisciLaCacheDellaGallery)->hourlyAt(17)->withoutOverlapping();

// Riconoscimento dei volti nella gallery. Le foto caricate dal pannello
// partono da sole; quelle arrivate per altre vie (l'import dell'archivio
// storico ne ha portate undicimila senza analizzarne una) le recupera questo
// giro, a blocchi che il worker smaltisce entro l'ora. Il limite conta anche
// i job già in coda: se il worker è in ritardo non si accumula.
Schedule::command('gallery:analyze --pending --limit=600 --force')->hourlyAt(37)->withoutOverlapping();

// Il contatore "Esempi AI" delle atlete è una copia di ciò che sta su
// CompreFace, che può essere azzerato o ricreato senza avvisare l'archivio.
Schedule::command('volti:riconcilia-contatori')->dailyAt('04:15')->withoutOverlapping();

// Pulizia periodica
Schedule::command('activity-log:prune --days=180 --force')->weekly()->withoutOverlapping();

// Il registro dei consensi ai cookie tiene dodici mesi, quanto dura il consenso
// che documenta: oltre, conservarlo sarebbe raccolta di dati senza scopo.
Schedule::command('consensi:pota')->weekly()->withoutOverlapping();

// Messaggi del modulo contatti e richieste di accredito: ventiquattro mesi, che
// è quello che l'informativa promette. Finché non c'è stato questo comando era
// l'unica conservazione dichiarata che nessuno applicava.
Schedule::command('messaggi:pota')->weekly()->withoutOverlapping();
Schedule::command('model:prune')->daily()->withoutOverlapping();
// I batch di analisi della gallery con `allowFailures()` non si chiudono mai
// da soli se un job fallisce: a settembre 2026 ce n'erano 18 aperti da luglio.
Schedule::command('queue:prune-batches --hours=48 --unfinished=72 --cancelled=72')->daily()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily()->withoutOverlapping();
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

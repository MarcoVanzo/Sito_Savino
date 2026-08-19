<?php

namespace App\Console\Commands;

use App\Models\AnalyticsSite;
use App\Services\Analytics\WebAnalyticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Aggiorna l'archivio locale del traffico dei siti.
 *
 * La serie giornaliera si salva già a ogni apertura della pagina, ma nessuno
 * garantisce che la pagina venga aperta: senza questo comando, un mese senza
 * visite al pannello sarebbe un mese perso per i confronti anno su anno. La
 * finestra è di 90 giorni per riprendersi anche i giorni che GA4 ha rielaborato.
 */
class SyncWebAnalytics extends Command
{
    protected $signature = 'analytics:sync-ga4 {--days=90 : Giorni da riportare in archivio}';

    protected $description = 'Salva in archivio la serie giornaliera di Google Analytics';

    public function handle(WebAnalyticsService $service): int
    {
        if (! $service->isConfigured()) {
            $this->warn('Service account GA4 non configurato: niente da sincronizzare.');

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $days = in_array($days, WebAnalyticsService::ALLOWED_DAYS, true) ? $days : 90;

        $sites = AnalyticsSite::query()->ordered()->get();

        if ($sites->isEmpty()) {
            $this->info('Nessun sito configurato.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($sites as $site) {
            // La cache serve a non ripetere le chiamate mentre qualcuno guarda la
            // pagina; qui il punto è proprio andare a chiedere dati nuovi.
            Cache::forget("ga4:overview:{$site->id}:{$site->property_id}:{$days}");

            // overview() salva la serie come effetto del suo lavoro normale:
            // qui interessa proprio quello.
            $data = $service->overview($site, $days);

            if ($data['ok']) {
                $this->info(sprintf('%s: %d giorni aggiornati.', $site->name, count($data['daily'])));

                continue;
            }

            $failed = true;
            $this->error(sprintf('%s: %s', $site->name, $data['error']['message'] ?? 'errore sconosciuto'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

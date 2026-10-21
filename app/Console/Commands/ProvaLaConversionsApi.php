<?php

namespace App\Console\Commands;

use App\Jobs\InviaAcquistoAMeta;
use App\Models\SiteSetting;
use App\Services\MetaConversioni\MetaConversioniClient;
use App\Services\MetaConversioni\MetaConversioniException;
use Illuminate\Console\Command;

/**
 * Manda a Meta un evento di prova, per verificare token e pixel senza un
 * ordine vero: con il codice di Events Manager → Test Events l'evento compare
 * solo li' e non entra nelle statistiche. Il token non esce mai dalle
 * variabili d'ambiente.
 */
class ProvaLaConversionsApi extends Command
{
    protected $signature = 'meta:prova-conversioni {codice : il codice di Test Events (TEST…)}';

    protected $description = 'Invia un evento di prova alla Conversions API di Meta (solo Test Events)';

    public function handle(): int
    {
        if ((string) config('services.meta.capi.token') === '') {
            $this->error('META_CAPI_TOKEN mancante.');

            return self::FAILURE;
        }

        $pixel = trim((string) SiteSetting::get('analytics.meta_pixel_id', ''));
        config(['services.meta.capi.test_event_code' => (string) $this->argument('codice')]);

        try {
            $risposta = MetaConversioniClient::fromConfig()->invia($pixel, [[
                'event_name' => 'PageView',
                'event_time' => now()->getTimestamp(),
                'event_id' => 'prova-'.now()->format('YmdHis'),
                'action_source' => 'website',
                'event_source_url' => url('/'),
                // Meta vuole almeno un dato del cliente oltre allo user agent:
                // senza risponde 400 «Invalid parameter».
                'user_data' => [
                    'client_user_agent' => 'meta:prova-conversioni',
                    'external_id' => [hash('sha256', 'meta:prova-conversioni')],
                ],
            ]]);
        } catch (MetaConversioniException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pixel {$pixel}: eventi ricevuti ".($risposta['events_received'] ?? '?').'. Conversions API '.(InviaAcquistoAMeta::accesa() ? 'accesa' : 'spenta').'.');

        return self::SUCCESS;
    }
}

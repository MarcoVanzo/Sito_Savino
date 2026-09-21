<?php

namespace App\Console\Commands;

use App\Http\Middleware\CachePublicResponse;
use App\Models\SiteSetting;
use Illuminate\Console\Command;

/**
 * Apre e chiude il negozio da riga di comando.
 *
 * `shop.enabled` si governa solo dalla pagina "Impostazioni Shop & Aste", e
 * quando il negozio è chiuso per sbaglio — com'è successo il 21/09/2026, con
 * il primo salvataggio di un modulo che si apriva vuoto — riaprirlo richiede
 * di entrare nel pannello con le credenziali di un super admin. Da fuori
 * resta il database, che in produzione si interroga in sola lettura, e la
 * console dell'app, dove una riga lunga arriva storpiata (il websocket
 * duplica caratteri: `siite_settings`, `typpe`). Da qui bastano tre parole.
 *
 * Senza argomento dice soltanto com'è adesso, e non scrive niente.
 */
class StatoDelloShop extends Command
{
    protected $signature = 'shop:stato {stato? : aperto oppure chiuso; senza argomento mostra lo stato attuale}';

    protected $description = 'Mostra, apre o chiude il negozio pubblico (shop.enabled)';

    public function handle(): int
    {
        $aperto = $this->eAperto();
        $richiesto = $this->argument('stato');

        if ($richiesto === null) {
            $this->line('Il negozio è '.($aperto ? '<info>aperto</info>' : '<comment>chiuso</comment>').'.');

            return self::SUCCESS;
        }

        $vuoleAperto = match (mb_strtolower($richiesto)) {
            'aperto', 'apri', 'on', '1' => true,
            'chiuso', 'chiudi', 'off', '0' => false,
            default => null,
        };

        if ($vuoleAperto === null) {
            $this->error('Stato non riconosciuto: usare "aperto" o "chiuso".');

            return self::FAILURE;
        }

        if ($vuoleAperto === $aperto) {
            $this->line('Il negozio era già '.($aperto ? 'aperto' : 'chiuso').': niente da fare.');

            return self::SUCCESS;
        }

        SiteSetting::set('shop.enabled', $vuoleAperto ? '1' : '0');

        // La pagina pubblica sta in cache per un minuto: senza questo, il
        // negozio riaprirebbe con quel ritardo e chi lancia il comando
        // penserebbe che non ha funzionato.
        CachePublicResponse::flush();

        $this->info('Negozio '.($vuoleAperto ? 'aperto' : 'chiuso').'.');

        return self::SUCCESS;
    }

    private function eAperto(): bool
    {
        // Lo stesso ripiego di ShopController::index: senza la riga in
        // archivio il negozio è aperto.
        return filter_var(SiteSetting::get('shop.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * La pagina Sponsor era vuota: in archivio non c'era un solo sponsor, e online
 * si vedeva una pagina senza loghi. La redazione ha chiesto di riportare quelli
 * del sito precedente, con i rispettivi collegamenti.
 *
 * Il comando `sponsors:import-legacy` fa esattamente questo ed è idempotente
 * (la chiave è il nome). Qui viene lanciato una volta sola, al primo deploy che
 * incontra questa migrazione: sono 76 sponsor con livello, link e logo.
 *
 * Tre cautele, perché le migrazioni girano a ogni avvio del container:
 *
 * - Se in archivio c'è già qualcosa non si tocca niente. Un import che passa
 *   sopra al lavoro fatto in redazione sarebbe peggio della pagina vuota.
 * - L'import legge un sito esterno: se quello non risponde, la migrazione lo
 *   annota e prosegue. Un deploy non deve fallire perché un sito altrui è giù.
 * - Sotto i test non parte affatto: lì il database si ricrea a ogni prova e
 *   la tabella è sempre vuota, quindi si finirebbe a interrogare un sito
 *   altrui centinaia di volte per nulla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing') || DB::table('sponsors')->exists()) {
            return;
        }

        try {
            Artisan::call('sponsors:import-legacy', ['--no-interaction' => true]);

            Log::info('Sponsor importati dal sito precedente', [
                'importati' => DB::table('sponsors')->count(),
            ]);
        } catch (Throwable $e) {
            // La pagina Sponsor resta vuota come prima e si potrà rilanciare
            // il comando a mano: nessun danno, solo un lavoro da rifare.
            Log::error('Import degli sponsor non riuscito', ['errore' => $e->getMessage()]);
        }
    }

    /**
     * Non reversibile: cancellare gli sponsor toglierebbe anche quelli
     * eventualmente aggiunti o corretti in redazione dopo l'import.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

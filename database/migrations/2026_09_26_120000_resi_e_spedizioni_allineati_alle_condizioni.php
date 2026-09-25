<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Due frasi delle pagine pratiche dello shop, pubblicate il 25/09/2026, non
 * tornavano con le Condizioni di vendita:
 *
 * - "Resi e rimborsi" escludeva dal recesso anche i prodotti sigillati per
 *   motivi igienici, che le Condizioni, l'informativa sul recesso e l'email
 *   di conferma non escludono; e prometteva che "la scheda del prodotto lo
 *   indica", mentre nessuna scheda lo fa. Un'esclusione scritta solo in una
 *   pagina pratica non vale contro il consumatore, e crea solo contestazioni.
 * - "Spedizioni" contava i tempi dalla spedizione, le Condizioni dalla
 *   ricezione del pagamento: ora la pagina dice che sono due tratti in fila.
 *
 * A guardia (§14): si riscrive solo la frase ancora uguale a quella
 * pubblicata; una pagina già corretta dalla redazione non si tocca.
 * `down()` non fa niente: rimettere un'esclusione sbagliata non ha senso.
 */
return new class extends Migration
{
    private const CORREZIONI = [
        [
            'slug' => 'resi-e-rimborsi',
            'vecchio' => 'Non si possono restituire per recesso i prodotti personalizzati su tua richiesta (per esempio con nome e numero a tua scelta) e i prodotti sigillati per motivi igienici aperti dopo la consegna. La scheda del prodotto lo indica prima dell\'acquisto.',
            'nuovo' => 'Non si possono restituire per recesso i prodotti personalizzati su tua richiesta, per esempio una maglia con nome e numero a tua scelta: la personalizzazione la chiedi tu prima dell\'acquisto, e solo quegli articoli sono esclusi.',
        ],
        [
            'slug' => 'resi-e-rimborsi',
            'vecchio' => 'Products personalised at your request (for example with a name and number of your choice) and sealed products unsealed after delivery for hygiene reasons cannot be returned on withdrawal. The product page says so before purchase.',
            'nuovo' => 'Products personalised at your request, for example a shirt with a name and number of your choice, cannot be returned on withdrawal: you ask for the personalisation before purchase, and only those items are excluded.',
        ],
        [
            'slug' => 'spedizioni',
            'vecchio' => 'I tempi indicati in tabella sono in giorni lavorativi dalla spedizione e sono indicativi:',
            'nuovo' => 'I tempi indicati in tabella sono in giorni lavorativi dalla spedizione (a cui si aggiungono i giorni di preparazione, che partono dalla ricezione del pagamento) e sono indicativi:',
        ],
        [
            'slug' => 'spedizioni',
            'vecchio' => 'The times in the table are working days from dispatch and are indicative:',
            'nuovo' => 'The times in the table are working days from dispatch (plus the preparation days, which start when payment is received) and are indicative:',
        ],
    ];

    public function up(): void
    {
        $toccata = false;

        foreach (self::CORREZIONI as $correzione) {
            $pagina = DB::table('pages')->where('slug', $correzione['slug'])->first(['id', 'content']);
            $contenuto = $pagina ? json_decode((string) $pagina->content, true) : null;

            if (! is_array($contenuto)) {
                continue;
            }

            $cambiato = false;

            foreach ($contenuto as $lingua => $testo) {
                if (is_string($testo) && str_contains($testo, $correzione['vecchio'])) {
                    $contenuto[$lingua] = str_replace($correzione['vecchio'], $correzione['nuovo'], $testo);
                    $cambiato = true;
                }
            }

            if ($cambiato) {
                DB::table('pages')->where('id', $pagina->id)->update([
                    'content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
                $toccata = true;
            }
        }

        if ($toccata) {
            CachePublicResponse::flush();
        }
    }

    public function down(): void
    {
        // Vedi sopra.
    }
};

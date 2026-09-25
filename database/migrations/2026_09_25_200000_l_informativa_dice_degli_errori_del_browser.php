<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anche gli errori JavaScript del sito arrivano a Sentry.
 *
 * La revisione precedente diceva che Sentry riceve l'indirizzo della pagina e
 * la traccia del guasto: era vero per il server. Dal browser arriva in più il
 * tipo di browser, e l'informativa lo dice, insieme al perché l'IP del
 * visitatore continua a non arrivare: gli eventi passano dal nostro server
 * (`/api/diagnostica`), e il browser non apre mai una connessione verso Sentry.
 *
 * Non alza `ConsensoCookie::VERSIONE` e non tocca la Cookie Policy: la
 * diagnostica non scrive niente nel browser.
 *
 * A guardia come le altre: si riscrive solo dove è rimasta una versione
 * precedente riconosciuta dalle `firme`. Se la redazione ha già messo mano
 * alla pagina, la frase su Sentry va aggiornata a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (TestiDelleInformative::tutte() as $slug => $informativa) {
            $pagina = DB::table('pages')->where('slug', $slug)->first();

            if (! $pagina) {
                continue;
            }

            $contenuti = json_decode((string) $pagina->content, true);

            if (! is_array($contenuti) || ! TestiDelleInformative::eAncoraUnTestoPrecedente($contenuti, $informativa['firme'])) {
                continue;
            }

            foreach (TestiDelleInformative::contenuto($slug) as $lingua => $testo) {
                $contenuti[$lingua] = $testo;
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                'content' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Non si annulla: il testo di prima non diceva che anche il browser
     * manda dati a Sentry.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

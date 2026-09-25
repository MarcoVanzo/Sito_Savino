<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sentry entra fra i responsabili del trattamento.
 *
 * Dal 25 settembre 2026 la sorveglianza degli errori e' accesa: quando
 * qualcosa si rompe, l'applicazione manda a Sentry l'indirizzo della pagina e
 * la traccia tecnica del guasto. E' un fornitore che riceve dati del
 * visitatore, quindi va dichiarato — anche se riceve poco.
 *
 * Quel «poco» e' una scelta di configurazione, non una speranza:
 * `send_default_pii` resta `false` ed e' scritto nello spec, quindi non
 * partono ne' l'indirizzo IP ne' l'identita' di chi stava navigando; i
 * parametri delle query (`sql_bindings`) restano spenti, o i dati di una riga
 * finirebbero nella traccia. L'informativa dice esattamente questo, e se un
 * domani quella configurazione cambia, va cambiata prima la pagina.
 *
 * Il progetto sta nella regione europea di Sentry, per questo la frase sui
 * trasferimenti fuori dall'Unione non lo nomina: sta con il sito, il database,
 * l'archivio fotografico e il riconoscimento dei volti.
 *
 * Non tocca la Cookie Policy e non alza `ConsensoCookie::VERSIONE`: Sentry non
 * lascia cookie nel browser e non c'e' niente da far riapprovare a nessuno.
 *
 * A guardia come le altre correzioni ai testi in produzione: si riscrive solo
 * dove e' rimasta una delle versioni precedenti, riconosciuta dalle `firme` del
 * file dati. Se la redazione ha gia' messo mano alla pagina non si tocca, e in
 * quel caso la riga dei responsabili va aggiunta a mano — perche' un fornitore
 * non dichiarato e' un fornitore che non dovrebbe ricevere niente.
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
     * Non si annulla: il testo di prima non nominava un fornitore che riceve
     * dati, ed e' il motivo per cui esiste questa migrazione.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

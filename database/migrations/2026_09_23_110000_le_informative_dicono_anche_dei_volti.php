<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revisione delle informative: quello che mancava alla riscrittura del 22.
 *
 * Il testo del 22 settembre raccontava il sito fino ai cookie. Restavano fuori:
 *
 * - il **riconoscimento dei volti** sull'archivio fotografico. È un trattamento
 *   biometrico su dodicimila immagini, con i nomi riconosciuti che finiscono
 *   nel titolo dell'immagine e quindi nei motori di ricerca, e non era
 *   dichiarato da nessuna parte;
 * - i **contenuti di terze parti** incorporati nelle pagine — la mappa del
 *   palazzetto, i video delle dirette — che si caricano insieme alla pagina e
 *   fanno arrivare l'IP del visitatore a Google, YouTube o Vimeo;
 * - il fornitore della **posta** (Resend), Stripe fra i gateway, il codice
 *   fiscale e il telefono raccolti dal checkout, lo storico delle password
 *   dell'area riservata;
 * - metà delle **conservazioni** che il sito applica davvero: carrelli a 7
 *   giorni, sessione a 2 ore, registro delle modifiche della redazione a 180.
 *
 * Nel frattempo i caratteri tipografici non arrivano più dal CDN di Google, che
 * vedeva l'IP di ogni visitatore prima di qualsiasi consenso: ora li serve il
 * sito, e per questo l'informativa non ha più niente da dire a riguardo.
 *
 * A guardia come le altre correzioni ai testi in produzione: si riscrive solo
 * dove è rimasta una delle versioni precedenti, riconosciuta dalle `firme` del
 * file dati. Se la redazione ha già messo mano alla pagina, non si tocca — e in
 * quel caso il nuovo testo va portato a mano, perché queste cose vanno dette.
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
     * Non si annulla: il testo di prima taceva trattamenti che il sito fa, ed è
     * il motivo per cui esiste questa migrazione.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

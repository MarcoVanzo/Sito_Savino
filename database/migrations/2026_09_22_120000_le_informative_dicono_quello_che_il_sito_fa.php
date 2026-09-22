<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Privacy Policy e Cookie Policy riscritte su quello che il sito fa davvero.
 *
 * Dicevano che il sito raccoglie "esclusivamente dati tecnici necessari alla
 * navigazione" e che "non utilizza cookie di profilazione o di tracciamento di
 * terze parti", mentre caricava Google Analytics 4 e il pixel di Meta: la
 * scansione dei cookie lo mostra riga per riga. L'indirizzo del titolare era
 * anche sbagliato (Via di Scandicci, Firenze, invece della sede di Scandicci
 * che sta nelle impostazioni del sito).
 *
 * A guardia, come le altre correzioni ai testi in produzione: si riscrive solo
 * dove è rimasto il testo vecchio, riconosciuto da una frase che solo quello
 * conteneva. Se la redazione ha già messo mano alla pagina, non si tocca.
 *
 * I testi stanno in `database/data/informative_privacy.php`, perché sono
 * contenuti e non logica, e da lì la redazione può continuare a modificarli
 * dal pannello.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (require database_path('data/informative_privacy.php') as $slug => $informativa) {
            $pagina = DB::table('pages')->where('slug', $slug)->first();

            if (! $pagina) {
                continue;
            }

            $contenuti = json_decode((string) $pagina->content, true);

            // Una pagina già riscritta a mano non porta più le firme del testo
            // vecchio: in quel caso questa migrazione non ha niente da fare.
            if (! is_array($contenuti) || ! $this->eAncoraIlTestoVecchio($contenuti, $informativa['firme'])) {
                continue;
            }

            foreach ($informativa['contenuto'] as $lingua => $testo) {
                $contenuti[$lingua] = $this->ripulisci($testo);
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                'content' => json_encode($contenuti, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $contenuti
     * @param  list<string>  $firme
     */
    private function eAncoraIlTestoVecchio(array $contenuti, array $firme): bool
    {
        $tutto = implode(' ', array_map('strval', $contenuti));

        foreach ($firme as $firma) {
            if (str_contains($tutto, $firma)) {
                return true;
            }
        }

        return false;
    }

    /**
     * L'heredoc del file dati è indentato per leggibilità: l'indentazione non
     * deve finire nell'HTML, dove l'editor del pannello la mostrerebbe come
     * spazi veri.
     */
    private function ripulisci(string $testo): string
    {
        return trim(preg_replace('/^[ \t]+/m', '', $testo) ?? $testo);
    }

    /**
     * Non si annulla: il testo di prima diceva il falso, ed è il motivo per cui
     * esiste questa migrazione.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

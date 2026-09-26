<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La dichiarazione di accessibilita' dopo i lavori del 26/09/2026 su checkout,
 * screen reader, archivio delle notizie e PDF: cade il limite "checkout non
 * coperto dal controllo automatico" (ora la scansione percorre carrello,
 * checkout, conferma d'ordine e asta su una copia di prova), cade quello
 * sulle notizie dell'archivio (titoli riallineati e testi alternativi scritti
 * dalle migrazioni 2026_09_26_170000 e 170100, che girano nello stesso
 * deploy), il limite sui PDF dice quali documenti e la verifica con lo screen
 * reader dice fin dove arriva.
 *
 * Il testo nuovo sta in `database/data/dichiarazione_accessibilita.php`. Si
 * scrive a guardia, lingua per lingua, come la migrazione precedente: solo se
 * il contenuto e' ancora esattamente quello che lei ha pubblicato (impronte
 * qui sotto, verificate sulla produzione in sola lettura il 26/09/2026). Se
 * la redazione l'ha modificato dal pannello, quella lingua non si tocca.
 */
return new class extends Migration
{
    public const SLUG = 'dichiarazione-di-accessibilita';

    /**
     * sha256 del testo pubblicato da 2026_09_26_110000_dichiarazione_di_accessibilita_rivista
     * (`git show bf9e479:database/data/dichiarazione_accessibilita.php`).
     */
    private const IMPRONTE_VERSIONE_PRECEDENTE = [
        'it' => 'e03a3d6740b62f6fd923aa84cf4ac9a0fb99329bfb52bd2cc90bae9507cae461',
        'en' => '8c731dd5db23d67f4ee6ef3a7bb417fbbe751c07e72e40909c996d43733d049c',
    ];

    public function up(): void
    {
        $riga = DB::table('pages')->where('slug', self::SLUG)->first(['id', 'content']);

        if ($riga === null) {
            return;
        }

        $attuale = json_decode((string) $riga->content, true);

        if (! is_array($attuale)) {
            return;
        }

        $nuovo = (require database_path('data/dichiarazione_accessibilita.php'))['contenuto'];
        $cambiate = 0;

        foreach (self::IMPRONTE_VERSIONE_PRECEDENTE as $lingua => $impronta) {
            $testo = $attuale[$lingua] ?? null;

            if (is_string($testo) && hash('sha256', $testo) === $impronta && isset($nuovo[$lingua])) {
                $attuale[$lingua] = $nuovo[$lingua];
                $cambiate++;
            }
        }

        if ($cambiate === 0) {
            return;
        }

        DB::table('pages')->where('id', $riga->id)->update([
            'content' => json_encode($attuale, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        CachePublicResponse::flush();
    }

    /**
     * Nessun ritorno: rimettere il testo precedente significherebbe ripubblicare
     * affermazioni non vere, e dopo `up()` non si distingue piu' una modifica
     * della redazione da quella di questa migrazione.
     */
    public function down(): void
    {
        //
    }
};

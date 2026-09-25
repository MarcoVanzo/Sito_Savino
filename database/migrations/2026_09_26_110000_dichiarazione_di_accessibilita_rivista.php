<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La dichiarazione di accessibilita' rivista: la prima versione (migrazione
 * 2026_09_26_100000) affermava piu' di quello che il sito faceva (contrasto
 * 4,5:1 ovunque, testo alternativo su tutte le immagini, tastiera ovunque),
 * scriveva la societa' con una denominazione abbreviata e legava il ricorso
 * ad AgID ai 30 giorni senza risposta, che e' il meccanismo della Legge
 * Stanca per la PA, non quello dei servizi soggetti all'EAA.
 *
 * Il testo nuovo sta in `database/data/dichiarazione_accessibilita.php`. Si
 * scrive a guardia, lingua per lingua: solo se il contenuto e' ancora
 * esattamente quello pubblicato dalla migrazione precedente, riconosciuto
 * dall'impronta qui sotto. Se la redazione l'ha gia' modificato dal pannello,
 * quella lingua non si tocca. Il confronto avviene sul testo decodificato e
 * non sulla stringa JSON della colonna, che il database puo' riscrivere
 * (spazi, ordine delle chiavi, escape).
 *
 * Solo la pagina `dichiarazione-di-accessibilita`: `accessibilita` e' la
 * pagina del palazzetto, curata dalla redazione.
 */
return new class extends Migration
{
    public const SLUG = 'dichiarazione-di-accessibilita';

    /**
     * sha256 del testo pubblicato il 26/09/2026 dalla migrazione precedente
     * (`git show 4e798ab:database/data/dichiarazione_accessibilita.php`).
     */
    private const IMPRONTE_VERSIONE_PRECEDENTE = [
        'it' => '692ab6b29e73f7ad1a6c1a7d5031cf860d189d55e594433777fa6a7884a46e02',
        'en' => 'f166e2ba64529fb7fa61eb474e40d57b896e2636e58b6838d05122d1983a629c',
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

<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * I link assoluti alle PAGINE del vecchio sito rimasti nel testo delle
 * notizie importate da WordPress (21 settembre 2026).
 *
 * `news:importa-i-media-dal-vecchio-sito` ha già portato sul nostro disco i
 * file sotto `/wp-content/uploads/` e riscritto quei riferimenti: qui restano
 * i link alle pagine, che nessuno tocca e che oggi funzionano soltanto perché
 * `savinodelbenevolley.it` serve ancora il sito vecchio. Il giorno in cui il
 * dominio passa a questo sito, gli slug non corrispondono più:
 * `/talentday/` qui è `talent-day` sotto `/youth`, `/Jam-Camp` non esiste, e
 * `/biglietteria/` risponde solo grazie alla rotta generica `/{slug}`, che
 * rimanda con un 301 all'indirizzo di sezione.
 *
 * Il censimento sul database di produzione ha trovato 39 notizie che citano il
 * dominio: 29 per i media, e delle restanti quasi tutte le occorrenze sono
 * indirizzi email (`info@`, `ticketing@`, `press@`), che non sono link e non
 * si toccano. I link alle pagine sono tre, in tre notizie:
 *
 *   9   "Believe! Al via la campagna abbonamenti 2025-2026"  /biglietteria/
 *   341 "TALENT DAY 2022 torna e rilancia"                   /talentday/
 *   439 "Jam Camp 2023. Ufficiali le date"                   /Jam-Camp
 *
 * `https://app.savinodelbenevolley.it/` (notizia 586) resta com'è: è la
 * web-app, un sottodominio esterno vero con un DNS suo, non una pagina di
 * questo sito.
 *
 * I due percorsi si chiedono a `route()` con il prefisso della lingua del
 * testo, non si scrivono a mano: un link interno scritto a mano perde il
 * prefisso, e se un domani la rotta cambia questa migrazione non l'ha
 * già cristallizzata nel contenuto.
 *
 * Ogni sostituzione porta con sé il contesto in cui è stata letta in
 * produzione: se la redazione nel frattempo ha riscritto quella frase, non la
 * si tocca. Rilanciarla non cambia nulla.
 */
return new class extends Migration
{
    /**
     * La frase del Jam Camp 2023: la pagina dedicata non esiste su questo sito
     * (il Summer Camp è un'altra cosa, e la sua pagina è in bozza), mentre il
     * modulo Google e l'indirizzo del camp sono già nel testo e funzionano.
     * L'indirizzo si toglie invece di puntare a una pagina che risponde 404.
     *
     * In produzione fra "dedicata" e l'indirizzo c'è uno spazio unificatore
     * (U+00A0), lasciato lì dall'editor di WordPress: cercare uno spazio
     * normale non trova la frase. Si accettano entrambi, perché il pannello
     * scrive lo spazio normale e la redazione potrebbe aver già ritoccato
     * quella riga.
     */
    private const SPAZI = [' ', "\u{A0}"];

    private const JAM_CAMP = [
        'Per maggiori informazioni visita la pagina dedicata%shttps://savinodelbenevolley.it/Jam-Camp o scrivi a ',
        'Per maggiori informazioni scrivi a ',
    ];

    public function up(): void
    {
        $lingue = config('app.supported_locales', ['it']);

        $notizie = DB::table('posts')
            ->where(function ($query) {
                $query->where('content', 'like', '%savinodelbenevolley.it%')
                    ->orWhere('excerpt', 'like', '%savinodelbenevolley.it%');
            })
            ->get(['id', 'slug', 'content', 'excerpt']);

        foreach ($notizie as $notizia) {
            $modifiche = [];

            foreach (['content', 'excerpt'] as $colonna) {
                $nuovo = $this->colonnaTradotta((string) $notizia->{$colonna});

                if ($nuovo !== null) {
                    $modifiche[$colonna] = $nuovo;
                }
            }

            if ($modifiche === []) {
                continue;
            }

            DB::table('posts')->where('id', $notizia->id)->update($modifiche + ['updated_at' => now()]);

            // Le migrazioni scrivono con il query builder, che non fa scattare
            // CacheInvalidationObserver: la pagina della notizia resterebbe in
            // cache con il link vecchio.
            foreach ($lingue as $lingua) {
                Cache::forget('public:news:'.$lingua.':'.$notizia->slug);
            }
        }

        CachePublicResponse::flush();
    }

    /**
     * Correzione di link rotti: tornare indietro li rimetterebbe online.
     */
    public function down(): void {}

    /**
     * La colonna translatable riscritta, o null se non è cambiato nulla.
     */
    private function colonnaTradotta(string $json): ?string
    {
        $lingue = json_decode($json, true);

        if (! is_array($lingue)) {
            return null;
        }

        $nuove = $lingue;

        foreach ($nuove as $lingua => $testo) {
            if (! is_string($testo) || $testo === '') {
                continue;
            }

            foreach ($this->sostituzioni((string) $lingua) as [$vecchio, $nuovo]) {
                $testo = str_replace($vecchio, $nuovo, $testo);
            }

            $nuove[$lingua] = $testo;
        }

        return $nuove === $lingue ? null : json_encode($nuove, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Le coppie [letto in produzione, da scrivere] per una lingua.
     *
     * I due indirizzi erano scritti nudi nel testo, non dentro un `<a>`:
     * lasciarli nudi significherebbe mostrare un percorso senza dominio, che
     * il lettore non può nemmeno cliccare. Diventano link con un'etichetta.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function sostituzioni(string $lingua): array
    {
        $coppie = [];

        foreach (self::SPAZI as $spazio) {
            $coppie[] = [sprintf(self::JAM_CAMP[0], $spazio), self::JAM_CAMP[1]];
        }

        $biglietteria = $this->percorso($lingua, 'ticketing.page', ['slug' => 'biglietteria']);

        if ($biglietteria !== null) {
            $coppie[] = [
                "<br />\nhttps://savinodelbenevolley.it/biglietteria/ </p>",
                "<br />\n<a href=\"{$biglietteria}\">Biglietteria</a></p>",
            ];
        }

        $talentDay = $this->percorso($lingua, 'youth.page', ['slug' => 'talent-day']);

        if ($talentDay !== null) {
            $coppie[] = [
                "<br />\nhttps://savinodelbenevolley.it/talentday/</p>",
                "<br />\n<a href=\"{$talentDay}\">Talent Day</a></p>",
            ];
        }

        return $coppie;
    }

    /**
     * Il percorso della rotta nella lingua del testo. Null se quella rotta non
     * esiste più: meglio lasciare il link com'è che scrivere un indirizzo
     * inventato.
     *
     * @param  array<string, string>  $parametri
     */
    private function percorso(string $lingua, string $nome, array $parametri): ?string
    {
        $nome = $lingua === config('app.fallback_locale') ? $nome : $lingua.'.'.$nome;

        return Route::has($nome) ? route($nome, $parametri, false) : null;
    }
};

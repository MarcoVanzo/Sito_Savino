<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * I testi alternativi delle immagini dentro le notizie importate da
 * WordPress (WCAG 1.1.1).
 *
 * Sul sito precedente tutte le immagini nel testo erano state caricate con
 * `alt=""`, cioè dichiarate decorative: uno screen reader le saltava. Ma 25 su
 * 26 non lo sono. Ci sono le tabelle dei prezzi degli abbonamenti, i gironi
 * delle coppe, le locandine con date e orari, le statistiche delle atlete che
 * salutano: informazioni che nel testo della notizia non ci sono o ci sono
 * solo in parte. I testi qui sotto li abbiamo scritti guardando ogni immagine
 * (26/09/2026), riportando quello che vi si legge e niente di più. Le foto di
 * persone non dicono nomi che l'immagine non mostra.
 *
 * Tutte le notizie con immagini hanno il solo testo italiano, quindi l'alt è
 * uno. La 27ª immagine (Talent Day, post 341) aveva già un alt, «Talent Day»,
 * e resta com'è: questa migrazione riempie soltanto gli alt vuoti.
 *
 * A guardie: si tocca un `<img>` solo se ha ancora quel `src` e `alt=""`. Se
 * la redazione ha già scritto un testo, o l'immagine non c'è più, quella
 * riga resta com'è. Il confronto sul `src` non passa da spazi o entità, che
 * negli indirizzi non ci sono; i tag importati non contengono U+00A0
 * (verificato sulle 26 righe).
 */
return new class extends Migration
{
    private const BASE = 'https://sito-savino-assets-2026.fra1.digitaloceanspaces.com/news/';

    /**
     * post id => [percorso dell'immagine sotto news/ => testo alternativo].
     *
     * @var array<int, array<string, string>>
     */
    public const TESTI = [
        7 => [
            '2025/07/Gironi-CEV-Champions-League-2025-2026-1-1024x576.png' => 'Gironi della quarta fase della CEV Champions League 2026 femminile. Pool A: VakifBank Istanbul, Savino Del Bene Scandicci, CS Volei Alba Blaj, vincente del terzo turno 15/16. Pool B: Fenerbahçe Medicana Istanbul, Grot Budowlani Łódź, Igor Gorgonzola Novara, vincente 13/14. Pool C: Numia Vero Volley Milano, Eczacibasi Istanbul, OK Železničar Lajkovac, vincente 11/12. Pool D: A. Carraro Prosecco DOC Conegliano, ŁKS Commercecon Łódź, Dresdner SC, Ankara Zeren Spor Kulübü. Pool E: Developres Rzeszów, SSC Palmberg Schwerin, Levallois Paris Saint Cloud, vincente 09/10.',
        ],
        9 => [
            '2025/08/Tabella-prezzi-25-26-1024x1024.jpg' => 'Believe, campagna abbonamenti 2025/2026: prezzi per settore (intero, prelazione, under 16). Tribuna Ovest 420, 370, 260 euro. Tribuna Est e Sud 360, 310, 220 euro. Tribuna Nord 310, 270, 190 euro. Sopraelevata Est 230, 180, 130 euro.',
        ],
        93 => [
            '2026/01/SM_03860-1024x683.jpg' => 'Foto di squadra della Savino Del Bene Volley: atlete e staff in giacca scura, camicia bianca e jeans, in posa su due file in campo, con i cartelloni Guess a bordo campo.',
        ],
        185 => [
            '2021/09/CITYiD_VOLLEY_scandicci-240x300.jpg' => 'La canotta Erreà Special Identity City iD della Savino Del Bene: blu e fucsia, con la scritta Scandicci sul petto e ripetuta nella grafica.',
            '2021/09/CITYiD_VOLLEY_IG_scandicci-2-169x300.jpg' => 'La stessa canotta City iD Scandicci vista di tre quarti.',
        ],
        198 => [
            '2021/10/foto2-300x200.jpg' => 'Azione di gioco al Trofeo Città di Scandicci: un attacco contro il muro a due avversario.',
            '2021/10/WhatsApp-Image-2021-10-01-at-17.36.21-300x200.jpeg' => 'Azione di gioco al Trofeo Città di Scandicci: una giocatrice in maglia blu schiaccia contro il muro di due avversarie in verde.',
        ],
        323 => [
            '2022/04/WhatsApp-Image-2022-04-10-at-18.03.17-300x200.jpeg' => 'Premiazione prima di gara 1: una giocatrice della Savino Del Bene, maglia numero 5, riceve una targa celebrativa.',
            '2022/04/WhatsApp-Image-2022-04-10-at-18.03.19-300x200.jpeg' => 'Premiazione prima di gara 1: una giocatrice della Savino Del Bene riceve un riconoscimento in cornice fra due dirigenti.',
        ],
        332 => [
            '2022/04/WhatsApp-Image-2022-04-24-at-19.02.49-1-169x300.jpeg' => 'Due atlete della selezione di Firenze in maglia gialla, con la medaglia al collo e la coppa del Trofeo dei Territori.',
            '2022/04/IMG_6046-1-300x200.jpg' => 'Quattro atlete della selezione di Firenze in maglia gialla, in posa davanti a un muro di mattoni.',
        ],
        376 => [
            '2022/10/Locandina_3TrofeoScandicci-211x300.jpg' => 'Locandina del 3° Trofeo Città di Scandicci, 15-16 ottobre 2022, Palazzetto dello Sport di Scandicci, via Rialdoli. Sabato 15 alle 16.30 Il Bisonte Firenze - E-Work Busto Arsizio, alle 19.30 Savino Del Bene Scandicci - Megabox Ond. Savio Vallefoglia. Domenica 16 alle 14.30 finale 3°-4° posto, alle 17.00 finale 1°-2° posto, poi presentazione della Savino Del Bene Scandicci. Ingresso giornaliero 5 euro, due giorni 8 euro, libero per gli abbonati.',
        ],
        377 => [
            '2022/10/70x100_TorneoScandicci_MOD-211x300.jpg' => 'Locandina del 3° Trofeo Città di Scandicci, 15-16 ottobre 2022, organizzato da Savino Del Bene Volley con Il Bisonte Firenze, al Palazzetto dello Sport di Scandicci. Sabato 15 alle 16.30 Il Bisonte Firenze - E-Work Busto Arsizio, alle 19.30 Savino Del Bene Scandicci - Megabox Ond. Savio Vallefoglia. Domenica 16 alle 14.30 finale 3°-4° posto, alle 17.30 finale 1°-2° posto, poi presentazione della squadra. Ingresso giornaliero 5 euro, due giorni 8 euro, libero per gli abbonati.',
        ],
        423 => [
            '2022/12/griglia-coppa-italia-1536x755-1-300x147.jpg' => 'Tabellone delle finali di Coppa Italia Frecciarossa, Unipol Arena di Bologna, 28 e 29 gennaio 2023. Quarti: Prosecco Doc Imoco Conegliano - Cuneo Granda S.Bernardo, Igor Gorgonzola Novara - Reale Mutua Fenera Chieri, Savino Del Bene Scandicci - Volley Bergamo 1991, Vero Volley Milano - TrasportiPesanti Casalmaggiore. Semifinali il 28 gennaio alle 18.30 e alle 21.00, finale il 29 gennaio alle 18.00.',
        ],
        491 => [
            '2023/05/46bb290e-aa4e-4c98-b0e5-c7c8c1223b9b-1024x683.jpg' => 'Una giocatrice della Savino Del Bene mostra il Gonfalone d\'Argento, la statuetta del Consiglio regionale della Toscana, davanti alle bandiere.',
        ],
        518 => [
            '2023/07/FASCE.jpg' => 'Fasce del sorteggio della CEV Champions League. Prima fascia: Imoco Conegliano, Vero Volley Milano, Fenerbahce Opet Istanbul, Eczacibasi Dynavit Istanbul, LKS Commercecon Lodz. Seconda fascia: Savino Del Bene Scandicci, VakifBank Istanbul, Developres Rzeszow, Grot Budowlani Lodz, Volero Le Cannet. Terza fascia: Volley Mulhouse Alsace, Allianz MTV Stuttgart, SC Potsdam, SC Prometey Dnipro, Maritza Plovdiv. Quarta fascia: Vasas Obuda Budapest, Calcit Kamnik, Jedinstvo Stara Pazova e due qualificate dagli Early Rounds.',
        ],
        519 => [
            '2023/07/POOL-B-CEV-Champions-League-FB-1024x576.png' => 'Pool B della CEV Champions League 2024: Eczacıbaşı Spor Kulübü, Savino Del Bene Volley, Maritza Plovdiv, Vasas Óbuda Budapest.',
        ],
        520 => [
            '2023/07/Tabella-costi-1024x300.jpg' => 'Prezzi degli abbonamenti per settore (intero, sconto in prelazione per gli ex abbonati, under 16 dai 6 ai 16 anni). Tribuna Ovest 350, 300, 175 euro. Tribuna Est e Sud 300, 250, 150 euro. Tribuna Nord 250, 200, 150 euro. Sopraelevata Est 200, 160, 100 euro.',
        ],
        525 => [
            '2023/08/Tabella-costi-1024x300.jpg' => 'Prezzi degli abbonamenti per settore (intero, sconto in prelazione per gli ex abbonati, under 16 dai 6 ai 16 anni). Tribuna Ovest 350, 300, 175 euro. Tribuna Est e Sud 300, 250, 150 euro. Tribuna Nord 250, 200, 150 euro. Sopraelevata Est 200, 160, 100 euro.',
        ],
        592 => [
            '2023/12/abbonamenti3-768x1024.jpg' => 'Abbonamenti per il girone di ritorno 2023-2024, prezzo adulti e under 16: Tribuna Ovest 200 e 125 euro, Tribuna Est e Sud 160 e 100 euro, Tribuna Nord 125 e 100 euro, Sopraelevata Est 100 e 60 euro; gratis sotto i 5 anni, previa mail. L\'abbonamento All Inclusive vale per le sei gare casalinghe del girone di ritorno di Serie A1 e per le eventuali gare casalinghe di play off, Coppa Italia e fase a eliminazione diretta della CEV Champions League.',
        ],
        668 => [
            '2024/05/Screenshot-2024-05-22-094300.png' => 'Le statistiche di Zhu Ting con la Savino Del Bene: Serie A1 63 presenze e 799 punti, Coppa Italia 3 e 49, CEV Champions League 7 e 82, CEV Cup 10 e 183. Totale 83 presenze e 1113 punti.',
        ],
        673 => [
            '2024/05/Screenshot-2024-05-28-162156.png' => 'Le statistiche di Haleigh Washington con la Savino Del Bene (presenze, punti, muri vincenti, ace): Serie A1 63, 330, 91, 23; CEV Champions League 6, 40, 8, 5; Coppa Italia 1, 8, 1, 0; CEV Cup 9, 79, 22, 10. Totale 79 presenze, 457 punti, 122 muri, 38 ace.',
        ],
        690 => [
            '2024/07/SORTEGGIO-CHAMPIONS-2025-1024x570.png' => 'Gironi della quarta fase della CEV Champions League 2025 femminile. Pool A: Imoco Conegliano, Developres Rzeszów, Maritza Plovdiv, vincente 09/10. Pool B: Eczacibasi Dynavit Istanbul, Levallois Paris Saint Cloud, SSC Palmberg Schwerin, vincente 03/04. Pool C: Vero Volley Milano, VakifBank Istanbul, Calcit Kamnik, vincente 07/08. Pool D: Fenerbahce Medicana Istanbul, Neptunes Nantes, Vasas Óbuda Budapest, vincente 01/02. Pool E: Savino Del Bene Scandicci, BKS Bostik ZGO Bielsko-Biała, Allianz MTV Stuttgart, vincente 05/06.',
        ],
        692 => [
            '2024/07/quadrato_FB_03-300x300.jpg' => 'Play as one, abbonamenti 2024-2025: prezzi per settore (intero, prelazione, under 16). Tribuna Ovest 390, 340, 220 euro. Tribuna Est e Sud 330, 280, 180 euro. Tribuna Nord 280, 230, 150 euro. Sopraelevata Est 210, 170, 100 euro. Sconto del 50% sul terzo abbonamento per i nuclei familiari, con una maglietta della stagione precedente in omaggio; per ogni abbonato sconti su merchandising, partner e trasferte, gadget e inviti a eventi.',
        ],
        951 => [
            '2026/07/CEV-Champions-League-Volley-2027-Women-Pool-4th-round-1024x576.png' => 'Gironi della quarta fase della CEV Champions League 2027 femminile, dal 24 novembre 2026 al 3 febbraio 2027. Pool A: VakifBank Istanbul, UNI Opole, Volley Mulhouse Alsace, vincente del terzo turno 09/10. Pool B: A. Carraro Prosecco DOC Conegliano, KS Developres Rzeszów, Dresdner SC, Igor Gorgonzola Novara. Pool C: Fenerbahçe Medicana Istanbul, Numia Vero Volley Milano, Levallois Paris Saint Cloud, Vasas Óbuda Budapest. Pool D: Savino Del Bene Scandicci, Eczacibasi Peron Istanbul, Tent Obrenovac, vincente 11/12. Pool E: PGE Budowlani Łódź, VfB Suhl Thüringen, C.S.O. Voluntari 2005, Galatasaray Daikin Istanbul.',
        ],
    ];

    public function up(): void
    {
        $toccate = [];

        foreach (self::TESTI as $id => $immagini) {
            $riga = DB::table('posts')->where('id', $id)->first(['id', 'slug', 'content']);

            if ($riga === null) {
                continue;
            }

            $lingue = json_decode((string) $riga->content, true);
            $testi = is_array($lingue) ? $lingue : ['*' => (string) $riga->content];
            $cambiata = false;

            foreach ($testi as $lingua => $html) {
                if (! is_string($html)) {
                    continue;
                }

                foreach ($immagini as $percorso => $alt) {
                    $nuovo = self::conAlt($html, self::BASE.$percorso, $alt);
                    if ($nuovo !== $html) {
                        $html = $nuovo;
                        $cambiata = true;
                    }
                }

                $testi[$lingua] = $html;
            }

            if (! $cambiata) {
                continue;
            }

            DB::table('posts')->where('id', $id)->update([
                'content' => is_array($lingue) ? json_encode($testi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $testi['*'],
            ]);
            $toccate[] = $riga->slug;
        }

        // La scrittura salta il modello e quindi CacheInvalidationObserver:
        // si buttano le schede delle notizie toccate, come fa
        // news:correggi-accessibilita, e la cache delle pagine pubbliche.
        foreach ($toccate as $slug) {
            foreach (config('app.supported_locales') as $locale) {
                Cache::forget('public:news:'.$locale.':'.$slug);
            }
        }

        if ($toccate !== []) {
            CachePublicResponse::flush();
        }
    }

    /** Riempie l'alt del solo `<img>` con quel `src`, e solo se è ancora vuoto. */
    public static function conAlt(string $html, string $src, string $alt): string
    {
        return preg_replace_callback(
            '/<img\b[^>]*\bsrc=("|\')'.preg_quote($src, '/').'\1[^>]*>/i',
            fn (array $m): string => preg_replace(
                '/\balt=("|\')\1/',
                'alt="'.htmlspecialchars($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"',
                $m[0],
                1,
            ) ?? $m[0],
            $html,
        ) ?? $html;
    }

    /**
     * Nessun ritorno: togliere gli alt rimetterebbe le immagini fra le
     * decorative, e dopo `up()` non si distingue un testo scritto qui da uno
     * corretto dalla redazione.
     */
    public function down(): void
    {
        //
    }
};

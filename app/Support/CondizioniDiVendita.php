<?php

namespace App\Support;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Condizioni di vendita e informativa sul recesso: un posto solo da cui
 * leggerle, come `TestiDelleInformative` per la privacy.
 *
 * I testi stanno in `database/data/condizioni_di_vendita.php`. Li usano il
 * seeder delle pagine, la migrazione che le crea in produzione e l'email di
 * conferma d'ordine, che deve portare al cliente le stesse cose che la pagina
 * dice (art. 51 c. 7 del Codice del consumo: la conferma su supporto durevole
 * contiene tutte le informazioni precontrattuali, modulo di recesso compreso).
 */
class CondizioniDiVendita
{
    /**
     * La versione delle condizioni che il cliente accetta al checkout: finisce
     * sull'ordine (`orders.condizioni_versione`), e dice quale testo valeva il
     * giorno dell'acquisto anche dopo che la redazione lo avrà riscritto.
     *
     * Si alza quando cambia la sostanza — recesso, garanzia, pagamenti — non a
     * ogni ritocco di stile. È un'etichetta leggibile, non la prova: il testo
     * esatto che il cliente aveva davanti lo dice l'impronta sull'ordine
     * (`orders.condizioni_impronta`, vedi registraIstantanea()), perché la
     * redazione può riscrivere le pagine dal pannello senza toccare il codice.
     */
    public const VERSIONE = '2026-09-25';

    /**
     * La ragione sociale per esteso: venditore (art. 49 c. 1 lett. b) e
     * titolare del trattamento (CLAUDE.md §21). Non e' l'impostazione
     * `contact.legal_ragione_sociale`, che tiene la forma abbreviata del
     * footer. I file dati dei testi legali ne hanno una copia propria: sono
     * testi datati, e cambiano solo con una revisione a guardie.
     */
    public const RAGIONE_SOCIALE = 'Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a Responsabilità Limitata';

    public const SLUG_CONDIZIONI = 'condizioni-di-vendita';

    public const SLUG_RECESSO = 'diritto-di-recesso';

    /**
     * @return array<string, array{titolo: array<string, string>, meta_description: array<string, string>, firme: list<string>, contenuto: array<string, string>}>
     */
    public static function tutte(): array
    {
        return require database_path('data/condizioni_di_vendita.php');
    }

    /**
     * Il testo per lingua, già ripulito e pronto da salvare.
     *
     * @return array<string, string>
     */
    public static function contenuto(string $slug): array
    {
        $pagina = self::tutte()[$slug] ?? null;

        if ($pagina === null) {
            return [];
        }

        return array_map(self::ripulisci(...), $pagina['contenuto']);
    }

    /**
     * Crea le pagine che mancano, e non tocca quelle che esistono già: se la
     * redazione ha scritto le sue condizioni, quelle vincono.
     *
     * @return list<string> gli slug creati
     */
    public static function creaLePagineMancanti(): array
    {
        $create = [];

        foreach (self::tutte() as $slug => $pagina) {
            if (DB::table('pages')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('pages')->insert([
                'title' => json_encode($pagina['titolo'], JSON_UNESCAPED_UNICODE),
                'slug' => $slug,
                'template' => 'Public/ContentPage',
                'status' => 'publish',
                'content' => json_encode(self::contenuto($slug), JSON_UNESCAPED_UNICODE),
                'meta_description' => json_encode($pagina['meta_description'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $create[] = $slug;
        }

        return $create;
    }

    /**
     * I dati del venditore come li chiede l'art. 49 c. 1 lett. b-c: la
     * ragione sociale per esteso e un recapito a cui scrivere. Li prende dalle
     * impostazioni del gruppo `contact`, come footer e informative; la ragione
     * sociale no. Dal 25/09/2026 esiste `contact.legal_ragione_sociale`, ma
     * contiene la forma abbreviata che il footer mostra accanto a REA e
     * capitale («… S.S.D. a r.l.»), mentre al consumatore va data per esteso
     * (`self::RAGIONE_SOCIALE`). Leggerla dall'impostazione cambierebbe il
     * venditore delle condizioni e del PDF allegato alla conferma d'ordine.
     *
     * @return array{ragione_sociale: string, indirizzo: string, piva: ?string, cf: ?string, rea: ?string, capitale: ?string, email: ?string, pec: ?string}
     */
    public static function venditore(): array
    {
        return [
            'ragione_sociale' => self::RAGIONE_SOCIALE,
            'indirizzo' => (string) (SiteSetting::get('contact.address') ?: 'Via Benozzo Gozzoli, 5/6 — 50018 Scandicci (FI)'),
            'piva' => SiteSetting::get('contact.legal_piva') ?: null,
            'cf' => SiteSetting::get('contact.legal_cf') ?: null,
            // Dalla visura camerale (art. 2250 c.c.), in Impostazioni → Contatti.
            'rea' => SiteSetting::get('contact.legal_rea') ?: null,
            'capitale' => SiteSetting::get('contact.legal_capitale') ?: null,
            'email' => SiteSetting::get('contact.email') ?: null,
            'pec' => SiteSetting::get('contact.pec') ?: null,
        ];
    }

    /**
     * L'indirizzo pubblico di una delle due pagine nella lingua dell'ordine.
     *
     * Serve alle email, che partono da un worker: fuori da una richiesta il
     * prefisso della lingua si chiede al nome della rotta, e la lingua
     * predefinita è `app.fallback_locale` (§23 del CLAUDE.md), non
     * `app.locale`, che durante una richiesta è già stata riscritta.
     */
    public static function indirizzo(string $slug, ?string $lingua = null): string
    {
        $lingua ??= config('app.fallback_locale', 'it');
        $rotta = $lingua === config('app.fallback_locale', 'it') ? 'pages.show' : $lingua.'.pages.show';

        if (! Route::has($rotta)) {
            $rotta = 'pages.show';
        }

        return route($rotta, ['slug' => $slug]);
    }

    /**
     * Il testo intero delle due pagine in una lingua, per l'allegato PDF della
     * conferma d'ordine.
     *
     * È il testo **pubblicato**, non quello del file dati: il cliente accetta
     * la pagina che legge, e la redazione può averla ritoccata dal pannello
     * dopo la creazione (le pagine da lì in poi sono sue). Il file dati resta
     * il ripiego per una pagina che manca o è vuota in quella lingua. I link
     * diventano assoluti: dentro un PDF `/recesso` non porta da nessuna parte.
     *
     * @return array<string, array{titolo: string, contenuto: string}>
     */
    public static function perLAllegato(string $lingua): array
    {
        $pagine = [];

        foreach (self::tutte() as $slug => $pagina) {
            $testi = self::contenuto($slug);
            $pubblicata = self::testoPubblicato($slug, $lingua);

            $pagine[$slug] = [
                'titolo' => $pagina['titolo'][$lingua] ?? $pagina['titolo']['it'],
                'contenuto' => self::linkAssoluti($pubblicata ?? $testi[$lingua] ?? $testi['it']),
            ];
        }

        return $pagine;
    }

    /**
     * Fotografa il testo che il cliente sta accettando e ne restituisce
     * l'impronta, da salvare su `orders.condizioni_impronta`.
     *
     * Il testo è lo stesso che finirebbe nel PDF (perLAllegato), in un unico
     * HTML con un segnaposto per pagina; l'impronta è lo sha256 di quell'HTML.
     * Una riga per impronta in `versioni_condizioni` (insertOrIgnore): finché
     * la redazione non tocca le pagine tutti gli ordini puntano alla stessa.
     */
    public static function registraIstantanea(string $lingua): string
    {
        $html = collect(self::perLAllegato($lingua))
            ->map(fn (array $pagina, string $slug) => '<!--pagina:'.$slug.'-->'.$pagina['contenuto'])
            ->implode("\n");
        $impronta = hash('sha256', $html);

        DB::table('versioni_condizioni')->insertOrIgnore([
            'impronta' => $impronta,
            'lingua' => $lingua,
            'html' => $html,
            'created_at' => now(),
        ]);

        return $impronta;
    }

    /**
     * Le pagine per il PDF allegato a un ordine: quelle fotografate quando il
     * cliente le ha accettate, se l'ordine ne ha l'impronta; altrimenti — gli
     * ordini nati prima del 26/09/2026 — il testo pubblicato oggi.
     *
     * @return array<string, array{titolo: string, contenuto: string}>
     */
    public static function perLAllegatoDellOrdine(Order $ordine): array
    {
        $lingua = $ordine->locale ?? 'it';
        $html = $ordine->condizioni_impronta
            ? DB::table('versioni_condizioni')->where('impronta', $ordine->condizioni_impronta)->value('html')
            : null;

        if (! is_string($html)) {
            return self::perLAllegato($lingua);
        }

        $parti = preg_split('/<!--pagina:([a-z0-9-]+)-->/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $tutte = self::tutte();
        $pagine = [];

        for ($i = 0; $i + 1 < count($parti); $i += 2) {
            $slug = $parti[$i];
            $pagine[$slug] = [
                'titolo' => $tutte[$slug]['titolo'][$lingua] ?? $tutte[$slug]['titolo']['it'] ?? $slug,
                'contenuto' => trim($parti[$i + 1]),
            ];
        }

        return $pagine ?: self::perLAllegato($lingua);
    }

    /**
     * Il contenuto della pagina com'è nel database, o null se non c'è. Letto
     * con DB e json_decode, non con spatie: una riga in testo semplice
     * sarebbe restituita come vuota (§9 del CLAUDE.md).
     */
    private static function testoPubblicato(string $slug, string $lingua): ?string
    {
        $grezzo = DB::table('pages')->where('slug', $slug)->where('status', 'publish')->value('content');

        if (! is_string($grezzo) || $grezzo === '') {
            return null;
        }

        $tradotto = json_decode($grezzo, true);
        $testo = is_array($tradotto) ? ($tradotto[$lingua] ?? $tradotto['it'] ?? null) : $grezzo;

        return is_string($testo) && trim(strip_tags($testo)) !== '' ? $testo : null;
    }

    private static function linkAssoluti(string $html): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return preg_replace('/\bhref="\/(?!\/)/', 'href="'.$base.'/', $html) ?? $html;
    }

    /**
     * L'heredoc del file dati è indentato per leggibilità: l'indentazione non
     * deve finire nell'HTML.
     */
    private static function ripulisci(string $testo): string
    {
        return trim(preg_replace('/^[ \t]+/m', '', $testo) ?? $testo);
    }
}

<?php

namespace App\Support;

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
     * ogni ritocco di stile.
     */
    public const VERSIONE = '2026-09-25';

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
     * impostazioni del gruppo `contact`, come footer e informative; il nome è
     * scritto qui perché nelle impostazioni c'è solo quello d'uso.
     *
     * @return array{ragione_sociale: string, indirizzo: string, piva: ?string, cf: ?string, rea: ?string, capitale: ?string, email: ?string, pec: ?string}
     */
    public static function venditore(): array
    {
        return [
            'ragione_sociale' => 'Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a Responsabilità Limitata',
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
     * conferma d'ordine: è quello che il file dati dice oggi, e la versione
     * accettata è scritta sull'ordine.
     *
     * @return array<string, array{titolo: string, contenuto: string}>
     */
    public static function perLAllegato(string $lingua): array
    {
        $pagine = [];

        foreach (self::tutte() as $slug => $pagina) {
            $testi = self::contenuto($slug);

            $pagine[$slug] = [
                'titolo' => $pagina['titolo'][$lingua] ?? $pagina['titolo']['it'],
                'contenuto' => $testi[$lingua] ?? $testi['it'],
            ];
        }

        return $pagine;
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

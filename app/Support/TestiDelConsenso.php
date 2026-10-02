<?php

namespace App\Support;

use App\Models\ConsensoCookie;
use App\Models\Page;
use App\Models\VersioneTestiConsenso;

/**
 * Che cosa il sito mostra a chi dà un consenso, nelle lingue del sito.
 *
 * La prova del consenso deve dire quali informazioni sono state date, non solo
 * che è stato detto sì (EDPB 05/2020 §107-108). Questi testi finiscono
 * nell'archivio `versioni_testi_consenso` (VersioneTestiConsenso::archivia):
 * si leggono dalle stesse fonti da cui li legge la pagina — le traduzioni del
 * frontend, la pagina CMS, la dichiarazione dei cookie — così un cambio fatto
 * dalla redazione o un rilascio produce da solo una versione nuova.
 *
 * Si prendono tutte le lingue e non solo quella del visitatore: la riga resta
 * la stessa per it ed en, e l'archivio non raddoppia a ogni testo.
 */
class TestiDelConsenso
{
    /** @var array<string, array<string, mixed>> */
    private static array $traduzioni = [];

    /**
     * Banner, pannello delle preferenze, Cookie Policy ed elenco dei cookie.
     *
     * @return array<string, mixed>
     */
    public static function perICookie(): array
    {
        $testi = ['versione' => ConsensoCookie::VERSIONE];

        foreach (self::lingue() as $lingua) {
            $frontend = self::traduzioni($lingua);

            $testi['lingue'][$lingua] = [
                // Tutta la sezione, non le sole chiavi usate oggi: una frase
                // aggiunta al banner domani entra nell'archivio senza che
                // qualcuno debba ricordarsi di elencarla qui.
                'banner' => $frontend['cookie'] ?? [],
                'link' => [
                    'privacy_policy' => $frontend['footer']['privacy_policy'] ?? null,
                    'cookie_policy' => $frontend['footer']['cookie_policy'] ?? null,
                ],
                'cookie_policy' => self::pagina('cookie-policy', $lingua),
                'dichiarazione' => DichiarazioneCookie::perIlFrontend($lingua),
            ];
        }

        return $testi;
    }

    /**
     * Il modulo d'iscrizione, la pagina e l'email di conferma, la Privacy
     * Policy a cui la casella rimanda.
     *
     * @return array<string, mixed>
     */
    public static function perLaNewsletter(): array
    {
        $testi = [];

        foreach (self::lingue() as $lingua) {
            $testi['lingue'][$lingua] = [
                'modulo' => self::traduzioni($lingua)['newsletter'] ?? [],
                'email_di_conferma' => trans('emails.newsletter_conferma', [], $lingua),
                'privacy_policy' => self::pagina('privacy-policy', $lingua),
            ];
        }

        return $testi;
    }

    /**
     * Archivia i testi della newsletter e ne restituisce l'impronta.
     */
    public static function archiviaPerLaNewsletter(): string
    {
        return VersioneTestiConsenso::archivia(VersioneTestiConsenso::TIPO_NEWSLETTER, self::perLaNewsletter());
    }

    /**
     * @return list<string>
     */
    private static function lingue(): array
    {
        return array_values(config('app.supported_locales', ['it']));
    }

    /**
     * Le traduzioni del frontend sono in JSON per Vite: le stesse che il
     * pacchetto JavaScript del rilascio ha incorporato.
     *
     * @return array<string, mixed>
     */
    private static function traduzioni(string $lingua): array
    {
        if (! isset(self::$traduzioni[$lingua])) {
            $file = resource_path('js/i18n/'.$lingua.'.json');
            $letto = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

            self::$traduzioni[$lingua] = is_array($letto) ? $letto : [];
        }

        return self::$traduzioni[$lingua];
    }

    /**
     * Il testo della pagina come lo legge il visitatore in quella lingua (con
     * il ripiego sull'italiano che usa anche il sito). Nullo se la pagina non
     * c'è o non è pubblicata: è un fatto da archiviare anche quello.
     *
     * @return array<string, mixed>|null
     */
    private static function pagina(string $slug, string $lingua): ?array
    {
        $pagina = Page::query()->where('slug', $slug)->published()->first();

        if (! $pagina) {
            return null;
        }

        return [
            'titolo' => $pagina->getTranslation('title', $lingua),
            'testo' => $pagina->getTranslation('content', $lingua),
            'contenuti' => $pagina->getTranslation('content_data', $lingua),
        ];
    }
}

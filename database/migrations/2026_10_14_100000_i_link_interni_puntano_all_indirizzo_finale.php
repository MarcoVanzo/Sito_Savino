<?php

use App\Http\Middleware\CachePublicResponse;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * I link interni puntano all'indirizzo finale, non a un rimando.
 *
 * Menu e footer portavano a indirizzi che rispondono 301/302 (`/ticketing`,
 * `/youth/u17`, `/shop/kit-gara`…): le rotte di rimando restano per i vecchi
 * link esterni, ma dal sito stesso ogni clic costava un giro in più e Google
 * contava i rimandi come link interni (audit SEO di ottobre 2026). Stessa cosa
 * per le CTA della home (`/ticketing`), per una slide (`/risultati`) e per i
 * suggerimenti del modulo contatti (`/ticketing`, `/youth`).
 *
 * A guardia: si riscrive solo un valore che e' ancora esattamente uno dei
 * vecchi indirizzi (barra finale a parte; query e frammento si conservano).
 * Un indirizzo gia' cambiato dalla redazione resta com'e'. L'elenco e'
 * fotografato qui: le destinazioni sono quelle delle rotte del 14/10/2026,
 * verificate sul sito vero.
 */
return new class extends Migration
{
    /**
     * Vecchio indirizzo => indirizzo finale.
     *
     * @var array<string, string>
     */
    private const RIMANDI = [
        '/stagione/news' => '/news',
        '/risultati' => '/stagione/risultati',
        '/classifica' => '/stagione/classifica',
        '/societa' => '/societa/storia',
        // La pagina Contatti vive su `/contatti` (`/en/contacts`).
        '/societa/contatti' => '/contatti',
        '/sponsor/nostri-sponsor' => '/sponsor',
        '/ticketing' => '/ticketing/biglietteria',
        '/youth' => '/youth/settore-giovanile',
        '/youth/b1-u19' => '/stagione/b1',
        '/youth/u17' => '/stagione/u17',
        '/youth/u15' => '/stagione/u15',
        '/youth/u17-u15' => '/stagione/u17',
        '/summer-camp/info' => '/summer-camp',
        '/summer-camp/iscrizione' => '/summer-camp',
        '/sociale' => '/sociale/volley-4-all',
        '/sociale/progetti' => '/sociale/progetti-sociali',
        '/sociale/aste' => '/shop/aste',
        // Voce di primo livello importata da WordPress, con l'accento.
        '/sostenibilità' => '/sociale/sostenibilita',
        '/comunicazione' => '/comunicazione/accrediti-stampa',
        '/comunicazione/accrediti' => '/comunicazione/accrediti-stampa',
        '/comunicazione/cartelle' => '/comunicazione/cartelle-stampa',
        '/shop/kit-gara' => '/shop/categoria/kit-gara',
        '/shop/abbigliamento' => '/shop/categoria/abbigliamento',
        '/shop/outlet' => '/shop/categoria/outlet',
        // Le pagine di sezione chiamate per slug dalla rotta generica
        // (`/diventa-sponsor`): PageController le rimanda alla loro sezione.
        '/organigramma' => '/societa/organigramma',
        '/storia' => '/societa/storia',
        '/safeguarding' => '/societa/safeguarding',
        '/palazzetto' => '/societa/palazzetto',
        '/club-race' => '/ticketing/club-race',
        '/abbonamenti' => '/ticketing/abbonamenti',
        '/biglietteria' => '/ticketing/biglietteria',
        '/convenzioni' => '/ticketing/convenzioni',
        '/accessibilita' => '/ticketing/accessibilita',
        '/diventa-sponsor' => '/sponsor/diventa-sponsor',
        '/title-sponsor' => '/sponsor/title-sponsor',
        '/hospitality' => '/sponsor/hospitality',
        '/settore-giovanile' => '/youth/settore-giovanile',
        '/talent-day' => '/youth/talent-day',
        '/affiliazioni' => '/youth/affiliazioni',
        '/iscrizione-experience' => '/summer-camp/iscrizione-experience',
        '/volley-4-all' => '/sociale/volley-4-all',
        '/progetti-sociali' => '/sociale/progetti-sociali',
        '/sostenibilita' => '/sociale/sostenibilita',
        '/progetto-scuola' => '/sociale/progetto-scuola',
        '/accrediti-stampa' => '/comunicazione/accrediti-stampa',
        '/cartelle-stampa' => '/comunicazione/cartelle-stampa',
        '/double-face' => '/comunicazione/double-face',
        '/magazine' => '/comunicazione/magazine',
    ];

    public function up(): void
    {
        $menu = $this->vociDelMenu();
        $impostazioni = $this->impostazioni();
        $slide = $this->slide();
        $pagine = $this->pagine();

        if ($menu > 0) {
            MenuItem::clearCache();
        }

        if ($impostazioni > 0) {
            SiteSetting::clearCache();
        }

        if ($menu + $impostazioni + $slide + $pagine > 0) {
            // Il menu viaggia nelle props di ogni pagina, salvata intera.
            CachePublicResponse::flush();
        }
    }

    /**
     * Non si annulla: i vecchi indirizzi erano il difetto.
     */
    public function down(): void {}

    /**
     * L'indirizzo finale, o null se il valore non e' uno dei vecchi.
     */
    private function finale(mixed $valore): ?string
    {
        if (! is_string($valore)) {
            return null;
        }

        $valore = trim($valore);

        if (! str_starts_with($valore, '/') || str_starts_with($valore, '//')) {
            return null;
        }

        $coda = '';
        $taglio = strcspn($valore, '?#');

        if ($taglio < strlen($valore)) {
            $coda = substr($valore, $taglio);
            $valore = substr($valore, 0, $taglio);
        }

        $percorso = rtrim($valore, '/');

        return isset(self::RIMANDI[$percorso]) ? self::RIMANDI[$percorso].$coda : null;
    }

    private function vociDelMenu(): int
    {
        $toccate = 0;

        foreach (DB::table('menu_items')->get(['id', 'url']) as $voce) {
            $finale = $this->finale($voce->url);

            if ($finale !== null) {
                DB::table('menu_items')->where('id', $voce->id)->update(['url' => $finale, 'updated_at' => now()]);
                $toccate++;
            }
        }

        return $toccate;
    }

    /**
     * Gli indirizzi delle CTA (`hero_cta2_url`, `cta_ticketing_url`…). Le
     * chiavi `json` tradotte non sono indirizzi: si guardano solo i valori
     * semplici.
     */
    private function impostazioni(): int
    {
        $toccate = 0;

        $righe = DB::table('site_settings')
            ->where(fn ($q) => $q->where('key', 'like', '%_url')->orWhere('key', 'like', '%_link'))
            ->get(['id', 'value']);

        foreach ($righe as $riga) {
            $finale = $this->finale($riga->value);

            if ($finale !== null) {
                DB::table('site_settings')->where('id', $riga->id)->update(['value' => $finale, 'updated_at' => now()]);
                $toccate++;
            }
        }

        return $toccate;
    }

    private function slide(): int
    {
        $toccate = 0;

        foreach (DB::table('hero_slides')->get(['id', 'cta_url']) as $slide) {
            $finale = $this->finale($slide->cta_url);

            if ($finale !== null) {
                DB::table('hero_slides')->where('id', $slide->id)->update(['cta_url' => $finale, 'updated_at' => now()]);
                $toccate++;
            }
        }

        if ($toccate > 0) {
            foreach (config('app.supported_locales', ['it', 'en']) as $lingua) {
                Cache::forget('public:home:'.$lingua);
            }
        }

        return $toccate;
    }

    /**
     * I campi indirizzo dentro `content_data` (`tip_link_url` del modulo
     * contatti, `button_url`…), in tutte le lingue. Si riscrive la riga solo
     * se qualcosa e' cambiato.
     */
    private function pagine(): int
    {
        $toccate = 0;

        foreach (DB::table('pages')->get(['id', 'slug', 'content_data']) as $pagina) {
            $lingue = json_decode((string) $pagina->content_data, true);

            if (! is_array($lingue)) {
                continue;
            }

            $nuove = $this->riscriviGliIndirizzi($lingue);

            if ($nuove === $lingue) {
                continue;
            }

            DB::table('pages')->where('id', $pagina->id)->update([
                'content_data' => json_encode($nuove, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            foreach (array_keys($nuove) as $lingua) {
                Cache::forget('public:page:'.$pagina->slug.':'.$lingua);
            }

            $toccate++;
        }

        return $toccate;
    }

    /**
     * @param  array<array-key, mixed>  $valori
     * @return array<array-key, mixed>
     */
    private function riscriviGliIndirizzi(array $valori): array
    {
        foreach ($valori as $chiave => $valore) {
            if (is_array($valore)) {
                $valori[$chiave] = $this->riscriviGliIndirizzi($valore);

                continue;
            }

            if (is_string($chiave) && preg_match('/(_url|_link)$/', $chiave)) {
                $valori[$chiave] = $this->finale($valore) ?? $valore;
            }
        }

        return $valori;
    }
};

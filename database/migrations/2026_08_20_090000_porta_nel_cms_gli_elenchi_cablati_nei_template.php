<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Progetti sociali, valori del vivaio, attività e turni del camp erano elencati
 * dentro i componenti Vue come valore di ripiego: le pagine li mostravano
 * online senza che in redazione esistesse niente da modificare.
 *
 * I componenti ora leggono solo dal CMS. Qui gli elenchi finiscono in tabella:
 * si prendono dalla pagina principale del template quando li ha (così le pagine
 * di sezione restano identiche a com'erano online) e altrimenti dal file dati
 * `database/data/page_template_defaults.php`, che contiene gli stessi elenchi
 * nelle due lingue. Il secondo caso è quello di un'installazione dove la pagina
 * principale non è mai stata compilata: senza, le sezioni sparirebbero.
 *
 * I listini (`plans`) restano esclusi di proposito: sono prezzi, e replicarli
 * su tre pagine significherebbe pubblicare tre listini da tenere allineati.
 */
return new class extends Migration
{
    private const ELENCHI = [
        'Public/Sociale' => ['sociale', ['projects', 'impact_stats']],
        'Public/Youth' => ['youth', ['values', 'youth_teams']],
        'Public/SummerCamp' => ['summer-camp', ['activities', 'dates', 'highlights']],
        'Public/Societa/Palazzetto' => ['palazzetto', ['services', 'venue_name', 'venue_address', 'maps_link', 'maps_iframe_src']],
    ];

    public function up(): void
    {
        $locales = config('app.supported_locales', ['it', 'en']);
        $iniziali = require database_path('data/page_template_defaults.php');

        foreach (self::ELENCHI as $template => [$slugPrincipale, $chiavi]) {
            $principale = DB::table('pages')->select('content_data')->where('slug', $slugPrincipale)->first();
            $sorgente = json_decode((string) ($principale->content_data ?? ''), true);
            $sorgente = is_array($sorgente) ? $sorgente : [];

            $pagine = DB::table('pages')
                ->select('id', 'slug', 'content_data')
                ->where('template', $template)
                ->get();

            foreach ($pagine as $pagina) {
                $data = json_decode((string) $pagina->content_data, true);
                $data = is_array($data) ? $data : [];
                $normalizzato = $this->perLingua($data, $locales);
                $modificata = $normalizzato !== $data;
                $data = $normalizzato;

                foreach ($locales as $locale) {
                    foreach ($chiavi as $chiave) {
                        if (! empty($data[$locale][$chiave])) {
                            continue;
                        }

                        // Si prende il primo valore **non vuoto**: `??` si ferma
                        // al primo non-null, e una chiave presente ma vuota
                        // (`services: []`) bloccherebbe il ripiego sui dati
                        // iniziali, lasciando la pagina senza contenuto.
                        $valore = collect([
                            $sorgente[$locale][$chiave] ?? null,
                            $sorgente['it'][$chiave] ?? null,
                            $iniziali[$template][$locale][$chiave] ?? null,
                            $iniziali[$template]['it'][$chiave] ?? null,
                        ])->first(fn ($candidato) => ! empty($candidato));

                        if (empty($valore)) {
                            continue;
                        }

                        $data[$locale][$chiave] = $valore;
                        $modificata = true;
                    }
                }

                if ($modificata) {
                    DB::table('pages')->where('id', $pagina->id)->update([
                        'content_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            }
        }
    }

    /**
     * Come sopra: una pagina rimasta senza il livello della lingua viene
     * ricondotta alla struttura translatable prima di aggiungere gli elenchi.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $locales
     * @return array<string, mixed>
     */
    private function perLingua(array $data, array $locales): array
    {
        if ($data === [] || array_diff(array_keys($data), $locales) === []) {
            return $data;
        }

        $normalizzato = [];

        foreach ($locales as $locale) {
            $normalizzato[$locale] = $data;
        }

        return $normalizzato;
    }

    public function down(): void
    {
        // no-op: senza questi elenchi le pagine tornerebbero a dipendere da
        // contenuti scritti nel codice.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La migrazione che ha portato gli elenchi nel CMS sceglieva il valore con una
 * catena di `??`, che si ferma al primo termine non-null: una chiave già
 * presente ma vuota (in produzione la pagina del palazzetto aveva
 * `services: []`) vinceva sui dati iniziali e la sezione restava senza
 * contenuto. Quella migrazione è già stata eseguita, quindi la correzione della
 * logica non basta: serve un secondo passaggio.
 *
 * Qui si riempiono, per ogni pagina, solo le chiavi previste dai dati iniziali
 * del suo template che risultano ancora vuote.
 */
return new class extends Migration
{
    public function up(): void
    {
        $iniziali = require database_path('data/page_template_defaults.php');
        $locales = config('app.supported_locales', ['it', 'en']);

        $pagine = DB::table('pages')
            ->select('id', 'slug', 'template', 'content_data')
            ->whereIn('template', array_keys($iniziali))
            ->get();

        foreach ($pagine as $pagina) {
            $data = json_decode((string) $pagina->content_data, true);
            $data = is_array($data) ? $data : [];

            // Una pagina rimasta senza il livello della lingua va ricondotta
            // alla struttura translatable prima di scriverci dentro.
            if ($data !== [] && array_diff(array_keys($data), $locales) !== []) {
                $piatto = $data;
                $data = [];

                foreach ($locales as $locale) {
                    $data[$locale] = $piatto;
                }
            }

            $modificata = false;

            foreach ($locales as $locale) {
                $valoriIniziali = $iniziali[$pagina->template][$locale]
                    ?? $iniziali[$pagina->template]['it']
                    ?? [];

                foreach ($valoriIniziali as $chiave => $valore) {
                    if (! empty($data[$locale][$chiave]) || empty($valore)) {
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

    public function down(): void
    {
        // no-op: togliere questi valori riporterebbe le pagine allo stato in cui
        // online non si vedeva nulla e in redazione non c'era niente da correggere.
    }
};

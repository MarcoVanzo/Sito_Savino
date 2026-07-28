<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Spezza la voce di menu "Classifica e Risultati" in due voci distinte.
 *
 * Classifica e risultati sono ora due pagine separate, come già lo sono nel CMS,
 * ma il menu principale vive nel database: senza questa migrazione la pagina
 * `/stagione/classifica` esisterebbe senza essere raggiungibile dalla
 * navigazione, e l'unica alternativa sarebbe una modifica a mano in produzione.
 *
 * Si lavora con il query builder e non con il modello: le migrazioni devono
 * restare valide anche se un domani `MenuItem` cambia. Le colonne tradotte
 * contengono un JSON {"it":…,"en":…}.
 *
 * La voce si cerca per URL e non per id, perché in produzione l'id può essere
 * diverso da quello del seeder.
 */
return new class extends Migration
{
    private const RESULTS_URLS = ['/stagione/risultati/', '/stagione/risultati'];

    private const STANDINGS_URL = '/stagione/classifica/';

    public function up(): void
    {
        $results = DB::table('menu_items')
            ->where('location', 'main')
            ->whereIn('url', self::RESULTS_URLS)
            ->first();

        if ($results === null) {
            // Menu personalizzato: non si indovina dove inserire la voce.
            return;
        }

        DB::table('menu_items')->where('id', $results->id)->update([
            'label' => json_encode(['it' => 'Risultati', 'en' => 'Results'], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        $alreadyPresent = DB::table('menu_items')
            ->where('location', 'main')
            ->whereIn('url', [self::STANDINGS_URL, rtrim(self::STANDINGS_URL, '/')])
            ->exists();

        if ($alreadyPresent) {
            $this->flushMenuCache();

            return;
        }

        $position = (int) $results->sort_order;

        // Le voci successive scalano di uno per fare posto alla classifica
        // subito sotto i risultati.
        DB::table('menu_items')
            ->where('location', 'main')
            ->where('parent_id', $results->parent_id)
            ->where('sort_order', '>', $position)
            ->increment('sort_order');

        DB::table('menu_items')->insert([
            'label' => json_encode(['it' => 'Classifica', 'en' => 'Standings'], JSON_UNESCAPED_UNICODE),
            'url' => self::STANDINGS_URL,
            'parent_id' => $results->parent_id,
            'location' => 'main',
            'sort_order' => $position + 1,
            'is_active' => true,
            'is_highlight' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->flushMenuCache();
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->where('location', 'main')
            ->whereIn('url', [self::STANDINGS_URL, rtrim(self::STANDINGS_URL, '/')])
            ->delete();

        DB::table('menu_items')
            ->where('location', 'main')
            ->whereIn('url', self::RESULTS_URLS)
            ->update([
                'label' => json_encode(['it' => 'Classifica e Risultati', 'en' => 'Standings and Results'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $this->flushMenuCache();
    }

    /**
     * Il menu è cachato per posizione e lingua con TTL di un giorno: senza
     * questo svuotamento la voce nuova comparirebbe fino a 24 ore dopo il deploy.
     */
    private function flushMenuCache(): void
    {
        foreach (config('app.supported_locales', ['it']) as $locale) {
            Cache::forget('menu_items_main_'.$locale);
            Cache::forget('menu_items_footer_'.$locale);
        }
    }
};

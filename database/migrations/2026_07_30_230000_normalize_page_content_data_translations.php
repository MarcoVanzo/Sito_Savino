<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Porta `pages.content_data` al formato translatable.
 *
 * La colonna è dichiarata translatable sul model, ma parte delle righe (quelle
 * scritte dai seeder) è salvata senza il livello della lingua:
 *
 *   corretto : {"it": {"hero": {...}}, "en": {"hero": {...}}}
 *   legacy   : {"hero": {...}}
 *
 * Su quelle righe spatie cerca la chiave della lingua, non la trova e
 * restituisce stringa vuota: il sito riceveva `content_data` vuoto e i template
 * ripiegavano sui valori di esempio scritti nel codice (fra cui nomi di
 * allenatori inventati e un listino biglietti fittizio).
 *
 * Il contenuto legacy viene copiato in entrambe le lingue: meglio l'italiano
 * anche sulle pagine inglesi che una sezione vuota. La redazione può poi
 * tradurre dal CMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        $locales = config('app.supported_locales', ['it', 'en']);

        DB::table('pages')
            ->select('id', 'content_data')
            ->orderBy('id')
            ->chunkById(100, function ($pages) use ($locales) {
                foreach ($pages as $page) {
                    $decoded = json_decode((string) $page->content_data, true);

                    if (! is_array($decoded) || $decoded === []) {
                        continue;
                    }

                    $keys = array_keys($decoded);

                    // Già suddiviso per lingua (tutte le chiavi di primo livello
                    // sono lingue supportate): si lascia com'è.
                    if (array_diff($keys, $locales) === []) {
                        continue;
                    }

                    // Forma mista, con una chiave di lingua accanto a chiavi di
                    // contenuto: avvolgerla creerebbe un doppio livello
                    // (content_data.it.it). Non è un caso previsto, si lascia
                    // intatta e si segnala per una revisione a mano.
                    if (array_intersect($keys, $locales) !== []) {
                        Log::warning(
                            "Pagina #{$page->id}: content_data ha una forma mista "
                            .'(chiavi di lingua e di contenuto insieme), lasciata invariata.'
                        );

                        continue;
                    }

                    $normalized = [];
                    foreach ($locales as $locale) {
                        $normalized[$locale] = $decoded;
                    }

                    DB::table('pages')
                        ->where('id', $page->id)
                        ->update(['content_data' => json_encode($normalized, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    /**
     * Nessun rollback: srotolare il livello della lingua significherebbe
     * scegliere quale delle due buttare via. Il formato translatable è quello
     * atteso dal model, tornare indietro sarebbe la regressione.
     */
    public function down(): void {}
};

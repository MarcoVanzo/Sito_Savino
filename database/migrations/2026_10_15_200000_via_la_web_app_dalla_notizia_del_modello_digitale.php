<?php

use App\Http\Middleware\CachePublicResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * La web-app `app.savinodelbenevolley.it` (Abilitya, 2023) esce dal sito:
 * scelta di Marco del 15/10/2026. È un sottodominio su Vercel che non è di
 * questo progetto, indicizzabile e senza un referente noto. Sul sito la cita
 * solo la notizia del 20/12/2023 «Savino Del Bene Volley lancia un nuovo
 * modello digitale» (586 in produzione), nella sezione finale «Esplora
 * l’universo digitale», che ha anche i link alle app di App Store e Google
 * Play: le due schede rispondono 404 (verificato il 15/10/2026).
 *
 * Si toglie quella sezione intera (titolo, link agli store, web-app); il
 * resto del comunicato resta com'è. Se la redazione ha riscritto il titolo,
 * si toglie almeno il paragrafo della web-app. Rilanciarla non cambia nulla.
 */
return new class extends Migration
{
    private const SEZIONE = '#<h2[^>]*>\s*(?:<b>)?\s*Esplora l.universo digitale.*?app\.savinodelbenevolley\.it.*?</p>\s*#su';

    private const PARAGRAFO = '#<p>(?:(?!</p>).)*app\.savinodelbenevolley\.it(?:(?!</p>).)*</p>\s*#su';

    public function up(): void
    {
        $lingue = config('app.supported_locales', ['it']);

        $notizie = DB::table('posts')
            ->where('content', 'like', '%app.savinodelbenevolley.it%')
            ->get(['id', 'slug', 'content']);

        foreach ($notizie as $notizia) {
            $testi = json_decode((string) $notizia->content, true);

            if (! is_array($testi)) {
                continue;
            }

            $nuovi = array_map(function ($testo) {
                if (! is_string($testo) || ! str_contains($testo, 'app.savinodelbenevolley.it')) {
                    return $testo;
                }

                $senza = preg_replace(self::SEZIONE, '', $testo, 1) ?? $testo;

                return $senza !== $testo ? $senza : (preg_replace(self::PARAGRAFO, '', $testo) ?? $testo);
            }, $testi);

            if ($nuovi === $testi) {
                continue;
            }

            DB::table('posts')->where('id', $notizia->id)->update([
                'content' => json_encode($nuovi, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            // Il query builder non fa scattare CacheInvalidationObserver.
            foreach ($lingue as $lingua) {
                Cache::forget('public:news:'.$lingua.':'.$notizia->slug);
            }
        }

        CachePublicResponse::flush();
    }

    /**
     * Rimettere il testo riporterebbe online link morti.
     */
    public function down(): void {}
};

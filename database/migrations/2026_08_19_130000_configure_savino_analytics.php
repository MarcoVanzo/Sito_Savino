<?php

use App\Models\AnalyticsSite;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configurazione iniziale delle analytics del sito.
 *
 * Sta in una migrazione e non in un seeder perché le migrazioni girano a ogni
 * deploy (`start.sh → migrate --force`), mentre i seeder no: senza, dopo il
 * rilascio qualcuno dovrebbe ricordarsi di aprire il pannello e incollare a
 * mano gli stessi due valori in ogni ambiente.
 *
 * Il Measurement ID non è un segreto: il tag di Google lo espone in chiaro nel
 * browser di chiunque visiti il sito. L'ID proprietà è un identificativo
 * interno di GA4 e da solo non dà accesso a nulla — i dati si leggono con il
 * service account, che sta nelle variabili d'ambiente.
 *
 * La riga dell'impostazione va creata con `group = 'analytics'`:
 * `SiteSetting::set()` scriverebbe il gruppo di default (`general`) e il valore
 * non finirebbe mai fra le impostazioni pubbliche condivise con il front-end.
 */
return new class extends Migration
{
    private const MEASUREMENT_ID = 'G-MZ6MT5576Y';

    private const META_PIXEL_ID = '2048882385693445';

    private const PROPERTY_ID = '550742878';

    private const SITE_NAME = 'Sito ufficiale';

    private const SITE_URL = 'https://www.savinodelbenevolley.it';

    public function up(): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'ga4_measurement_id'],
            [
                'value' => self::MEASUREMENT_ID,
                'type' => 'text',
                'group' => 'analytics',
                'label' => 'Measurement ID GA4',
                'description' => 'Identificativo del tag di misurazione (G-XXXXXXXXXX). Vuoto = nessuna misurazione.',
                'sort_order' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        // Lo stesso pixel già attivo sul sito precedente: riusarlo tiene
        // continui i pubblici di retargeting e lo storico delle conversioni,
        // che con un pixel nuovo ripartirebbero da zero.
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'meta_pixel_id'],
            [
                'value' => self::META_PIXEL_ID,
                'type' => 'text',
                'group' => 'analytics',
                'label' => 'ID Pixel di Meta',
                'description' => 'Identificativo del pixel pubblicitario. Vuoto = pixel non caricato.',
                'sort_order' => 2,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        // Solo se non c'è ancora nulla: se qualcuno ha già configurato i siti dal
        // pannello, questa migrazione non deve rimettere le mani in una scelta
        // fatta a mano.
        if (AnalyticsSite::query()->exists()) {
            return;
        }

        AnalyticsSite::query()->create([
            'name' => self::SITE_NAME,
            'property_id' => self::PROPERTY_ID,
            'url' => self::SITE_URL,
            'sort' => 0,
        ]);
    }

    /**
     * Non reversibile di proposito: togliere la configurazione spegnerebbe la
     * misurazione senza che nessuno abbia chiesto di spegnerla, e la serie
     * storica agganciata al sito sparirebbe con la riga.
     */
    public function down(): void {}
};

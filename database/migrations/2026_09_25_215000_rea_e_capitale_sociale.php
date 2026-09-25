<?php

use App\Http\Middleware\CachePublicResponse;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Denominazione, REA e capitale sociale nei dati societari.
 *
 * Una societa' di capitali deve indicare anche sul sito la sede, l'ufficio
 * del registro delle imprese con il numero di iscrizione e il capitale
 * effettivamente versato (art. 2250 c.c.); lo shop li deve dare al
 * consumatore prima dell'ordine (art. 7 d.lgs. 70/2003). Il footer mostrava
 * solo la partita IVA. I valori vengono dalla visura camerale del 07/08/2025:
 * REA FI-624279, iscrizione al Registro Imprese di Firenze con il numero del
 * codice fiscale, capitale deliberato, sottoscritto e versato 150.000,00 euro.
 *
 * Le chiavi si creano qui, nel gruppo `contact`: `SiteSetting::set()` le
 * metterebbe nel gruppo predefinito e non arriverebbero al frontend. Una
 * chiave che esiste gia' non si tocca.
 */
return new class extends Migration
{
    private const VALORI = [
        'legal_ragione_sociale' => ['Ragione sociale', 'Pallavolo Scandicci Savino Del Bene S.S.D. a r.l.'],
        'legal_rea' => ['Numero REA', 'FI-624279'],
        'legal_capitale' => ['Capitale sociale', '€ 150.000,00 i.v.'],
    ];

    public function up(): void
    {
        $create = 0;

        foreach (self::VALORI as $chiave => [$etichetta, $valore]) {
            if (DB::table('site_settings')->where('key', $chiave)->exists()) {
                continue;
            }

            DB::table('site_settings')->insert([
                'key' => $chiave,
                'value' => $valore,
                'type' => 'text',
                'group' => 'contact',
                'label' => $etichetta,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $create++;
        }

        if ($create > 0) {
            SiteSetting::clearCache();
            CachePublicResponse::flush();
        }
    }

    public function down(): void
    {
        DB::table('site_settings')->where('group', 'contact')->whereIn('key', array_keys(self::VALORI))->delete();
        SiteSetting::clearCache();
    }
};

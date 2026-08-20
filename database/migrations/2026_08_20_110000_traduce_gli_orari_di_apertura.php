<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gli orari di apertura erano una stringa sola: su /en/contacts si leggeva
 * "Lun-Ven: 09:00-18:00" sotto l'etichetta inglese. È l'unico recapito fatto di
 * parole — email, indirizzo e partite IVA non si traducono.
 *
 * Il valore diventa un JSON per lingua, come le altre impostazioni testuali;
 * `SiteSetting::resolveForLocale()` lo risolve già da sé.
 */
return new class extends Migration
{
    private const CHIAVE = 'office_hours';

    /** Traduzione dei formati che il valore può avere oggi. */
    private const GIORNI = [
        'Lun' => 'Mon', 'Mar' => 'Tue', 'Mer' => 'Wed', 'Gio' => 'Thu',
        'Ven' => 'Fri', 'Sab' => 'Sat', 'Dom' => 'Sun',
    ];

    public function up(): void
    {
        $riga = DB::table('site_settings')->where('key', self::CHIAVE)->first();

        if ($riga === null) {
            return;
        }

        $valore = (string) $riga->value;
        $decodificato = json_decode($valore, true);

        // Già tradotto: non si tocca, la redazione potrebbe averci messo mano.
        if (is_array($decodificato) && isset($decodificato['it'])) {
            return;
        }

        DB::table('site_settings')->where('key', self::CHIAVE)->update([
            'value' => json_encode(
                ['it' => $valore, 'en' => strtr($valore, self::GIORNI)],
                JSON_UNESCAPED_UNICODE
            ),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $riga = DB::table('site_settings')->where('key', self::CHIAVE)->first();
        $decodificato = $riga === null ? null : json_decode((string) $riga->value, true);

        if (! is_array($decodificato) || ! isset($decodificato['it'])) {
            return;
        }

        DB::table('site_settings')->where('key', self::CHIAVE)->update([
            'value' => (string) $decodificato['it'],
            'updated_at' => now(),
        ]);
    }
};

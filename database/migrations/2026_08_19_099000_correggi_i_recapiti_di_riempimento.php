<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alcuni recapiti erano rimasti ai valori di esempio dello sviluppo: il telefono
 * era "+39 055 XXX XXXX" e come sede compariva il palazzetto invece della sede
 * legale. Vengono sostituiti con quelli pubblicati dalla società, ma solo dove
 * il valore attuale è ancora quello di riempimento: un dato già corretto in
 * redazione non viene toccato.
 */
return new class extends Migration
{
    /**
     * chiave => [valore da correggere, valore giusto]
     */
    private const CORREZIONI = [
        'phone' => ['+39 055 XXX XXXX', '055 721503'],
        'address' => ['PalaBigmat, Via Allende 10, Firenze', 'Via Benozzo Gozzoli, 5/6 — 50018 Scandicci (FI)'],
        'email' => ['info@savinodelbenescandicci.it', 'info@savinodelbenevolley.it'],
        'city' => ['Scandicci (FI), Toscana', 'Scandicci (FI)'],
    ];

    public function up(): void
    {
        foreach (self::CORREZIONI as $chiave => [$daCorreggere, $giusto]) {
            DB::table('site_settings')
                ->where('key', $chiave)
                ->where(function ($query) use ($daCorreggere) {
                    $query->where('value', $daCorreggere)->orWhereNull('value')->orWhere('value', '');
                })
                ->update(['value' => $giusto, 'updated_at' => now()]);
        }

        SiteSetting::clearCache();
    }

    public function down(): void
    {
        // no-op: i valori precedenti erano segnaposto.
    }
};

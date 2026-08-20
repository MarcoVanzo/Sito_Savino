<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il frontend leggeva `general.site_logo` per l'immagine in testata e nel
 * footer, ma quella voce non è mai esistita in tabella: il logo restava quello
 * di `Constants/logos.js` e dal pannello non c'era modo di cambiarlo.
 *
 * La riga va creata qui e non lasciata al primo salvataggio: `SiteSetting::set()`
 * crea le chiavi nuove nel gruppo predefinito, e una voce finita nel gruppo
 * sbagliato non arriverebbe al frontend.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('site_settings')->where('key', 'site_logo')->exists()) {
            return;
        }

        DB::table('site_settings')->insert([
            'key' => 'site_logo',
            // Vuoto di proposito: senza un'immagine caricata vale il logo
            // ufficiale della società, che resta il riferimento di brand.
            'value' => '',
            'type' => 'text',
            'group' => 'general',
            'label' => 'Logo del sito',
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SiteSetting::clearCache();
    }

    public function down(): void
    {
        DB::table('site_settings')->where('key', 'site_logo')->delete();
    }
};

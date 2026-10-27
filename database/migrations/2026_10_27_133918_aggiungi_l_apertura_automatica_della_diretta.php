<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'interruttore che fa aprire da sola la diretta in home, nel gruppo
 * `diretta` (`SiteSetting::set()` metterebbe la chiave nel gruppo
 * predefinito, CLAUDE.md §14). Nasce spento: lo accende la comunicazione.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('site_settings')->where('key', 'diretta_apertura_automatica')->exists()) {
            return;
        }

        DB::table('site_settings')->insert([
            'key' => 'diretta_apertura_automatica',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'diretta',
            'label' => 'Apri la diretta da sola in home',
            'sort_order' => (int) DB::table('site_settings')->where('group', 'diretta')->max('sort_order') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SiteSetting::clearCache();
    }

    public function down(): void
    {
        DB::table('site_settings')->where('key', 'diretta_apertura_automatica')->delete();
        SiteSetting::clearCache();
    }
};

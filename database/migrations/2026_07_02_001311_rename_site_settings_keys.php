<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rinomina le chiavi site_settings per rimuovere i prefissi ridondanti
     * e allineare i nomi alle chiavi usate dal frontend.
     *
     * Gruppo 'home': rimuove prefisso 'home_' e rinomina per coerenza con Home.vue
     * Gruppo 'contact': rimuove prefisso 'contact_' (già nel namespace del gruppo)
     */
    public function up(): void
    {
        // ── HOME group: rinomina per corrispondere alle chiavi in Home.vue ──
        $homeRenames = [
            'home_hero_title'          => 'hero_title',
            'home_hero_title_accent'   => 'hero_subtitle',
            'home_hero_claim'          => 'hero_tagline',
            'home_cta_primary_text'    => 'hero_cta1_label',
            'home_cta_primary_url'     => 'hero_cta1_url',
            'home_cta_secondary_text'  => 'hero_cta2_label',
            'home_cta_secondary_url'   => 'hero_cta2_url',
            'home_stats'               => 'stats',
            'home_cta_ticketing_title' => 'cta_ticketing_title',
            'home_cta_ticketing_text'  => 'cta_ticketing_text',
            'home_cta_ticketing_url'   => 'cta_ticketing_url',
            'home_cta_shop_title'      => 'cta_shop_title',
            'home_cta_shop_text'       => 'cta_shop_text',
            'home_cta_shop_url'        => 'cta_shop_url',
        ];

        foreach ($homeRenames as $old => $new) {
            DB::table('site_settings')
                ->where('key', $old)
                ->update(['key' => $new]);
        }

        // ── CONTACT group: rimuove prefisso ridondante 'contact_' ──
        $contactRenames = [
            'contact_email'   => 'email',
            'contact_phone'   => 'phone',
            'contact_pec'     => 'pec',
            'contact_address' => 'address',
            'contact_city'    => 'city',
        ];

        foreach ($contactRenames as $old => $new) {
            DB::table('site_settings')
                ->where('key', $old)
                ->update(['key' => $new]);
        }

        // ── Aggiunge chiavi mancanti usate dal frontend ──
        $now = now();

        $newSettings = [
            [
                'key'        => 'stats_title',
                'value'      => 'Il Club in Numeri',
                'type'       => 'text',
                'group'      => 'home',
                'label'      => 'Titolo Sezione Statistiche',
                'sort_order' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key'        => 'stats_subtitle',
                'value'      => 'I Numeri',
                'type'       => 'text',
                'group'      => 'home',
                'label'      => 'Sottotitolo Sezione Statistiche',
                'sort_order' => 16,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($newSettings as $setting) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting
            );
        }

        // Invalida cache dopo rename
        cache()->forget('site_settings');
        cache()->forget('site_settings_grouped');
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        // ── HOME group: ripristina prefissi ──
        $homeRenames = [
            'hero_title'          => 'home_hero_title',
            'hero_subtitle'       => 'home_hero_title_accent',
            'hero_tagline'        => 'home_hero_claim',
            'hero_cta1_label'     => 'home_cta_primary_text',
            'hero_cta1_url'       => 'home_cta_primary_url',
            'hero_cta2_label'     => 'home_cta_secondary_text',
            'hero_cta2_url'       => 'home_cta_secondary_url',
            'stats'               => 'home_stats',
            'cta_ticketing_title' => 'home_cta_ticketing_title',
            'cta_ticketing_text'  => 'home_cta_ticketing_text',
            'cta_ticketing_url'   => 'home_cta_ticketing_url',
            'cta_shop_title'      => 'home_cta_shop_title',
            'cta_shop_text'       => 'home_cta_shop_text',
            'cta_shop_url'        => 'home_cta_shop_url',
        ];

        foreach ($homeRenames as $old => $new) {
            DB::table('site_settings')
                ->where('key', $old)
                ->update(['key' => $new]);
        }

        // ── CONTACT group: ripristina prefissi ──
        $contactRenames = [
            'email'   => 'contact_email',
            'phone'   => 'contact_phone',
            'pec'     => 'contact_pec',
            'address' => 'contact_address',
            'city'    => 'contact_city',
        ];

        foreach ($contactRenames as $old => $new) {
            DB::table('site_settings')
                ->where('key', $old)
                ->update(['key' => $new]);
        }

        // Rimuove chiavi aggiunte
        DB::table('site_settings')->whereIn('key', ['stats_title', 'stats_subtitle'])->delete();

        cache()->forget('site_settings');
        cache()->forget('site_settings_grouped');
    }
};

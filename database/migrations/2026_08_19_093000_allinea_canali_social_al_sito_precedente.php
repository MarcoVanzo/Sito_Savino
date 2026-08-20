<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il sito precedente esponeva sette canali social; qui ne erano configurati
 * cinque, due dei quali senza indirizzo. Vengono aggiunti LinkedIn e il canale
 * WhatsApp e riempiti X e TikTok — senza toccare le voci già valorizzate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $channels = [
            ['key' => 'social_instagram', 'value' => 'https://www.instagram.com/savinodelbenevolley/', 'label' => 'Instagram', 'sort_order' => 0],
            ['key' => 'social_facebook', 'value' => 'https://www.facebook.com/savinodelbenevolley/', 'label' => 'Facebook', 'sort_order' => 1],
            ['key' => 'social_youtube', 'value' => 'https://www.youtube.com/channel/UCyHpswavR-Rs6ssmF4BvLCQ', 'label' => 'YouTube', 'sort_order' => 2],
            ['key' => 'social_x', 'value' => 'https://x.com/sdbvolley', 'label' => 'X (Twitter)', 'sort_order' => 3],
            ['key' => 'social_tiktok', 'value' => 'https://www.tiktok.com/@savinodelbenescandicci', 'label' => 'TikTok', 'sort_order' => 4],
            ['key' => 'social_linkedin', 'value' => 'https://www.linkedin.com/company/savino-del-bene-volley/', 'label' => 'LinkedIn', 'sort_order' => 5],
            ['key' => 'social_whatsapp', 'value' => 'https://whatsapp.com/channel/0029VasgCCu3WHTcri3MjL2W', 'label' => 'Canale WhatsApp', 'sort_order' => 6],
        ];

        foreach ($channels as $channel) {
            $existing = DB::table('site_settings')->where('key', $channel['key'])->first();

            if ($existing && filled($existing->value)) {
                continue;
            }

            DB::table('site_settings')->updateOrInsert(
                ['key' => $channel['key']],
                [
                    'value' => $channel['value'],
                    'type' => 'url',
                    'group' => 'social',
                    'label' => $channel['label'],
                    'sort_order' => $channel['sort_order'],
                    'updated_at' => now(),
                    'created_at' => $existing->created_at ?? now(),
                ]
            );
        }

        // Le impostazioni pubbliche sono in cache: senza questo, in produzione
        // i nuovi canali comparirebbero solo alla scadenza della cache.
        SiteSetting::clearCache();
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', ['social_linkedin', 'social_whatsapp'])->delete();
    }
};

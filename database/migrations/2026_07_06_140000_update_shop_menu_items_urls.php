<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update Shop menu items to point to real pages instead of /in-costruzione.
     */
    public function up(): void
    {
        // ID 46: Kit Gara → categoria
        DB::table('menu_items')
            ->where('id', 46)
            ->update(['url' => '/shop/categoria/kit-gara-25-26']);

        // ID 47: Abbigliamento & Accessori → categoria abbigliamento
        DB::table('menu_items')
            ->where('id', 47)
            ->update(['url' => '/shop/categoria/abbigliamento']);

        // ID 48: Aste & Outlet → categoria asta
        DB::table('menu_items')
            ->where('id', 48)
            ->update(['url' => '/shop/categoria/asta']);

        // ID 49: Guida Taglie & Contatti → solo Guida Taglie
        DB::table('menu_items')
            ->where('id', 49)
            ->update([
                'label' => json_encode(['it' => 'Guida Taglie', 'en' => 'Size Guide']),
                'url' => '/shop/guida-taglie',
                'sort_order' => 3,
            ]);

        // Nuova voce: Contatti Shop (sotto Shop Ufficiale, id 45)
        $exists = DB::table('menu_items')
            ->where('parent_id', 45)
            ->whereRaw("JSON_EXTRACT(label, '$.it') = ?", ['Contatti Shop'])
            ->exists();

        if (! $exists) {
            DB::table('menu_items')->insert([
                'label' => json_encode(['it' => 'Contatti Shop', 'en' => 'Shop Contacts']),
                'url' => '/shop/contatti',
                'parent_id' => 45,
                'sort_order' => 4,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Ripristina gli URL originali
        DB::table('menu_items')->where('id', 46)->update(['url' => '/in-costruzione']);
        DB::table('menu_items')->where('id', 47)->update(['url' => '/in-costruzione']);
        DB::table('menu_items')->where('id', 48)->update(['url' => '/in-costruzione']);
        DB::table('menu_items')->where('id', 49)->update([
            'label' => json_encode(['it' => 'Guida Taglie & Contatti', 'en' => 'Size Guide & Contacts']),
            'url' => '/in-costruzione',
            'sort_order' => 3,
        ]);

        // Rimuovi la voce Contatti Shop aggiunta
        DB::table('menu_items')
            ->where('parent_id', 45)
            ->whereRaw("JSON_EXTRACT(label, '$.it') = ?", ['Contatti Shop'])
            ->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Due richieste della redazione sullo shop.
 *
 * "Aste & Outlet" era una voce sola che portava alle aste: l'outlet è merce a
 * prezzo ridotto, le aste sono le aste, e vanno separate. La voce diventa
 * "Outlet" e accanto compare "Aste".
 *
 * Kit Gara mostrava tutte le maglie insieme senza permettere di scegliere fra
 * Home, Away e Champions. Il meccanismo delle linguette c'è già e si accende da
 * solo quando la categoria ha delle sottocategorie con dentro dei prodotti:
 * qui si creano le tre sottocategorie, i prodotti ce li sposta la redazione.
 */
return new class extends Migration
{
    private const SOTTOCATEGORIE = [
        'kit-gara-home' => ['it' => 'Home', 'en' => 'Home'],
        'kit-gara-away' => ['it' => 'Away', 'en' => 'Away'],
        'kit-gara-champions' => ['it' => 'Champions', 'en' => 'Champions'],
    ];

    public function up(): void
    {
        $this->separaAsteDaOutlet();
        $this->preparaLeSottocategorieDelKitGara();
    }

    public function down(): void
    {
        DB::table('product_categories')->whereIn('slug', array_keys(self::SOTTOCATEGORIE))->delete();
    }

    private function separaAsteDaOutlet(): void
    {
        $outlet = DB::table('menu_items')
            ->whereIn('url', ['/shop/outlet/', '/shop/outlet'])
            ->first();

        if (! $outlet) {
            return;
        }

        DB::table('menu_items')
            ->where('id', $outlet->id)
            ->update(['label' => json_encode(['it' => 'Outlet', 'en' => 'Outlet'], JSON_UNESCAPED_UNICODE)]);

        // L'aggiunta è idempotente: al secondo passaggio la voce esiste già.
        $asteEsistono = DB::table('menu_items')
            ->whereIn('url', ['/shop/aste/', '/shop/aste'])
            ->exists();

        if ($asteEsistono) {
            return;
        }

        DB::table('menu_items')->insert([
            'label' => json_encode(['it' => 'Aste', 'en' => 'Auctions'], JSON_UNESCAPED_UNICODE),
            'url' => '/shop/aste/',
            'location' => $outlet->location,
            'parent_id' => $outlet->parent_id,
            'sort_order' => $outlet->sort_order + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function preparaLeSottocategorieDelKitGara(): void
    {
        $kitGara = DB::table('product_categories')->where('slug', 'kit-gara-25-26')->first();

        if (! $kitGara) {
            return;
        }

        $posizione = 0;

        foreach (self::SOTTOCATEGORIE as $slug => $nome) {
            $posizione++;

            if (DB::table('product_categories')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('product_categories')->insert([
                'name' => json_encode($nome, JSON_UNESCAPED_UNICODE),
                'slug' => $slug,
                'parent_id' => $kitGara->id,
                'sort_order' => $posizione,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};

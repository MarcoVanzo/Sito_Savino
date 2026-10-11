<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TrackShopPageView registrava le visite allo shop con il solo indirizzo in
 * `metadata.url`, senza il prodotto: il grafico «Prodotti più visti», che
 * raggruppa su viewable, restava vuoto. Da ora il middleware scrive il
 * prodotto; qui si ricollegano le visite già registrate, leggendo lo slug
 * dall'indirizzo (`/shop/prodotto/<slug>`, `/en/shop/product/<slug>`).
 *
 * Tocca solo le visite ancora senza prodotto: rilanciata non cambia nulla.
 * Gli slug che non corrispondono a nessun prodotto restano come sono.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prodotti = DB::table('products')->pluck('id', 'slug');

        DB::table('shop_events')
            ->where('event_type', 'view')
            ->whereNull('viewable_id')
            ->where(fn ($q) => $q
                ->where('metadata->url', 'like', '%/shop/prodotto/%')
                ->orWhere('metadata->url', 'like', '%/shop/product/%'))
            ->select(['id', 'metadata'])
            ->chunkById(500, function ($righe) use ($prodotti): void {
                foreach ($righe as $riga) {
                    $url = json_decode((string) $riga->metadata, true)['url'] ?? '';

                    if (! preg_match('#/shop/(?:prodotto|product)/([^/?\#]+)#', $url, $trovato)) {
                        continue;
                    }

                    $id = $prodotti[rawurldecode($trovato[1])] ?? null;

                    if ($id === null) {
                        continue;
                    }

                    DB::table('shop_events')->where('id', $riga->id)->update([
                        'viewable_type' => Product::class,
                        'viewable_id' => $id,
                    ]);
                }
            });
    }

    /**
     * Non si annulla: il prodotto è quello dell'indirizzo visitato, e il
     * middleware lo scrive comunque per le visite nuove.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

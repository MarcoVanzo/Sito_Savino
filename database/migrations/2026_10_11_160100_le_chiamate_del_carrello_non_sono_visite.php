<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TrackShopPageView contava come visita ogni GET dello shop, comprese le
 * chiamate JSON che il carrello fa a ogni pagina (`carrello/count`,
 * `carrello/data`): circa 119 000 righe, l'85% delle «visite», che falsavano
 * il tasso di conversione di Analytics Shop. Da ora il middleware conta solo
 * le pagine; qui si tolgono quelle righe, che non erano visite.
 *
 * Si cancella a blocchi per non tenere la tabella sotto lock. Rilanciata non
 * trova più nulla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $chiamateDelCarrello = fn () => DB::table('shop_events')
            ->where('event_type', 'view')
            ->where(fn ($q) => $q
                ->where('metadata->url', 'like', '%/shop/carrello/count%')
                ->orWhere('metadata->url', 'like', '%/shop/carrello/data%')
                ->orWhere('metadata->url', 'like', '%/shop/cart/count%')
                ->orWhere('metadata->url', 'like', '%/shop/cart/data%'));

        do {
            $ids = $chiamateDelCarrello()->limit(5000)->pluck('id');

            DB::table('shop_events')->whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());
    }

    /**
     * Non si annulla: le righe tolte non erano visite.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

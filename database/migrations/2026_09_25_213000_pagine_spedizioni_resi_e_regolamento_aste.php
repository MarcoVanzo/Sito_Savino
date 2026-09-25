<?php

use App\Http\Middleware\CachePublicResponse;
use App\Support\PagineLegaliDelloShop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spedizioni, resi e regolamento delle aste, portati dal vecchio negozio
 * WooCommerce (shop.savinodelbenevolley.it) e aggiornati: vedi
 * `database/data/condizioni_shop.php`. Le condizioni di vendita e il recesso
 * li crea la migrazione precedente (`CondizioniDiVendita`).
 *
 * Quelle che esistono gia' non si toccano. Il regolamento delle aste diventa
 * una pagina, tradotta e modificabile come le altre: l'impostazione
 * `auctions.rules_text` resta come ripiego e non si cancella.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (PagineLegaliDelloShop::creaQuelleCheMancano() !== []) {
            CachePublicResponse::flush();
        }
    }

    /**
     * Si tolgono solo le pagine che nessuno ha modificato dopo la creazione:
     * le altre sono lavoro della redazione.
     */
    public function down(): void
    {
        foreach (array_keys(PagineLegaliDelloShop::tutte()) as $slug) {
            DB::table('pages')->where('slug', $slug)->whereColumn('updated_at', 'created_at')->delete();
        }
    }
};

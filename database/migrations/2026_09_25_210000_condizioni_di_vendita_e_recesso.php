<?php

use App\Support\CondizioniDiVendita;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo shop vendeva senza condizioni di vendita e senza informativa sul recesso:
 * senza la seconda il termine per recedere si allunga di dodici mesi (art. 53
 * del Codice del consumo). Le due pagine nascono pubblicate, con i testi di
 * `database/data/condizioni_di_vendita.php`; se la redazione ne avesse già
 * creata una con lo stesso slug, resta la sua.
 *
 * Sull'ordine si segna quale versione delle condizioni il cliente ha accettato:
 * il testo della pagina cambierà, l'ordine deve continuare a dire a quale si
 * riferiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        CondizioniDiVendita::creaLePagineMancanti();

        if (! Schema::hasColumn('orders', 'condizioni_versione')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('condizioni_versione', 20)->nullable()->after('privacy_accepted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'condizioni_versione')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('condizioni_versione');
            });
        }

        // Solo le pagine che nessuno ha piu' salvato dopo la creazione: una
        // pagina modificata dalla redazione e' lavoro suo, e un rollback non
        // deve cancellarlo (come in pagine_spedizioni_resi_e_regolamento_aste).
        DB::table('pages')
            ->whereIn('slug', [CondizioniDiVendita::SLUG_CONDIZIONI, CondizioniDiVendita::SLUG_RECESSO])
            ->where('template', 'Public/ContentPage')
            ->whereColumn('updated_at', 'created_at')
            ->delete();
    }
};

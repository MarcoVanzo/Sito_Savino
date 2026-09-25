<?php

use App\Http\Middleware\CachePublicResponse;
use App\Support\InformativeDaDocumento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'informativa sulle comunicazioni promozionali e quella fornitori diventano
 * pagine, come le altre del footer: vedi
 * `database/data/informative_da_documento.php`.
 *
 * Le impostazioni `legal.informativa_promozionale` e `legal.informativa_fornitori`
 * non si toccano: non le legge più nessuno, e i PDF restano su Spaces come
 * copia dei documenti originali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (InformativeDaDocumento::creaQuelleCheMancano() !== []) {
            CachePublicResponse::flush();
        }
    }

    /**
     * Si tolgono solo le pagine che nessuno ha modificato dopo la creazione.
     */
    public function down(): void
    {
        foreach (array_keys(InformativeDaDocumento::tutte()) as $slug) {
            DB::table('pages')->where('slug', $slug)->whereColumn('updated_at', 'created_at')->delete();
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La pagina Progetto Affiliazioni mostrava ancora il testo segnaposto del
 * seeder ("Programma Affiliazioni... formazione tecnica, condivisione di
 * metodologie").
 *
 * I testi veri, presi dal sito precedente, stanno gia' in
 * `database/data/revisione_agosto_contenuti.php`, ma la migrazione del 20
 * agosto li scriveva solo su una pagina vuota, e questa vuota non era: aveva
 * il segnaposto. Qui il segnaposto viene riconosciuto e sostituito; un testo
 * scritto dalla redazione non si tocca.
 */
return new class extends Migration
{
    private const SEGNAPOSTO = 'programma di affiliazione per società sportive e scuole di pallavolo del territorio';

    public function up(): void
    {
        $pagina = DB::table('pages')->where('slug', 'affiliazioni')->first();

        if (! $pagina) {
            return;
        }

        $attuale = is_string($pagina->content) ? json_decode($pagina->content, true) : null;
        $italiano = is_array($attuale) ? (string) ($attuale['it'] ?? '') : (string) $pagina->content;

        if (trim(strip_tags($italiano)) !== '' && ! str_contains($italiano, self::SEGNAPOSTO)) {
            return;
        }

        $contenuti = require database_path('data/revisione_agosto_contenuti.php');

        DB::table('pages')->where('id', $pagina->id)->update([
            'content' => json_encode($contenuti['affiliazioni'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function down(): void
    {
        // Il segnaposto non e' un contenuto da ripristinare.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La versione inglese della pagina del palazzetto indica un indirizzo diverso da
 * quella italiana: "Via del Tridente, 5 — 50127 Florence (FI)", che è il testo
 * di esempio del campo nel pannello, mentre l'impianto sta in Via del
 * Cavallaccio. Chi legge il sito in inglese trova un indirizzo che non esiste.
 *
 * Si sostituisce l'indirizzo dove compare — nel corpo della pagina e nel campo
 * `venue_address` — lasciando stare tutto il resto.
 */
return new class extends Migration
{
    private const SBAGLIATO = 'Via del Tridente, 5 — 50127 Florence (FI)';

    private const GIUSTO = 'Via del Cavallaccio, 18/20/22/24 — 50142 Florence (FI)';

    public function up(): void
    {
        $this->sostituisci(self::SBAGLIATO, self::GIUSTO);
    }

    public function down(): void
    {
        $this->sostituisci(self::GIUSTO, self::SBAGLIATO);
    }

    private function sostituisci(string $da, string $a): void
    {
        foreach (['content', 'content_data'] as $colonna) {
            DB::table('pages')
                ->where('slug', 'palazzetto')
                ->where($colonna, 'like', '%'.$da.'%')
                ->update([$colonna => DB::raw(sprintf(
                    'REPLACE(`%s`, %s, %s)',
                    $colonna,
                    DB::getPdo()->quote($da),
                    DB::getPdo()->quote($a),
                ))]);
        }
    }
};

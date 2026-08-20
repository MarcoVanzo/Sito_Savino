<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'impianto si chiama "Pala BigMat" e la capienza dichiarata è di oltre 3.500
 * posti: è quello che la redazione ha scritto nel corpo della pagina, mentre il
 * resto del sito — testi tradotti, dati iniziali, dati strutturati, meta
 * description — era rimasto su "PalaBigmat" e 4.000 posti.
 *
 * A schermo la differenza si sarebbe vista tutta insieme: il titolo della
 * pagina prende il nome dal campo del pannello e il paragrafo sotto dal corpo,
 * quindi le due grafie sarebbero finite una sopra l'altra.
 *
 * La sostituzione è testuale e mirata: solo le forme censite, e ripeterla non
 * cambia niente perché la nuova grafia non contiene la vecchia.
 */
return new class extends Migration
{
    /** Colonne di testo in cui l'impianto viene nominato. */
    private const COLONNE = [
        'pages' => ['content', 'content_data', 'excerpt', 'meta_description', 'meta_title'],
        'site_settings' => ['value'],
    ];

    /**
     * Le forme trovate nei contenuti. Il numero non si sostituisce da solo
     * ("4.000" secco comparirebbe anche altrove): si sostituisce la frase.
     */
    private const TESTI = [
        'PalaBigmat' => 'Pala BigMat',
        '4.000 posti' => '3.500 posti',
        '4,000 seats' => '3,500 seats',
        'Capienza 4000 Posti' => 'Capienza 3500 Posti',
        '4000 Seats Capacity' => '3500 Seats Capacity',
        'Capacity 4000 Seats' => 'Capacity 3500 Seats',
        '4.000+' => '3.500+',
    ];

    public function up(): void
    {
        $this->applica(self::TESTI);
    }

    public function down(): void
    {
        $this->applica(array_flip(self::TESTI));
    }

    /**
     * @param  array<string, string>  $testi
     */
    private function applica(array $testi): void
    {
        foreach (self::COLONNE as $tabella => $colonne) {
            foreach ($colonne as $colonna) {
                foreach ($testi as $da => $a) {
                    DB::table($tabella)
                        ->where($colonna, 'like', '%'.$da.'%')
                        ->update([$colonna => DB::raw(sprintf(
                            'REPLACE(`%s`, %s, %s)',
                            $colonna,
                            DB::getPdo()->quote($da),
                            DB::getPdo()->quote($a),
                        ))]);
                }
            }
        }
    }
};

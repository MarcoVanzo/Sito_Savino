<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Spedizioni, resi e regolamento delle aste: le pagine pratiche dello shop,
 * con i testi in `database/data/condizioni_shop.php`. Condizioni di vendita e
 * recesso sono di `CondizioniDiVendita`.
 *
 * Le usano la migrazione che le pubblica in produzione e il seeder delle
 * pagine, così un ambiente nuovo — e il database dei test — nasce con gli
 * stessi testi. Una pagina che esiste già non si tocca: da quando c'e' e' della
 * redazione, che la modifica dal pannello (Pagine).
 */
class PagineLegaliDelloShop
{
    public const SPEDIZIONI = 'spedizioni';

    public const RESI = 'resi-e-rimborsi';

    public const REGOLAMENTO_ASTE = 'regolamento-aste';

    /**
     * @return array<string, array{titolo: array<string, string>, descrizione: array<string, string>, firme: list<string>, contenuto: array<string, string>}>
     */
    public static function tutte(): array
    {
        return require database_path('data/condizioni_shop.php');
    }

    /**
     * Crea le pagine che mancano.
     *
     * @return list<string> gli slug creati
     */
    public static function creaQuelleCheMancano(): array
    {
        $create = [];

        foreach (static::tutte() as $slug => $pagina) {
            if (DB::table('pages')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('pages')->insert([
                'title' => json_encode($pagina['titolo'], JSON_UNESCAPED_UNICODE),
                'slug' => $slug,
                'template' => 'Public/ContentPage',
                'content' => json_encode($pagina['contenuto'], JSON_UNESCAPED_UNICODE),
                'meta_description' => json_encode($pagina['descrizione'], JSON_UNESCAPED_UNICODE),
                'status' => 'publish',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $create[] = $slug;
        }

        return $create;
    }
}

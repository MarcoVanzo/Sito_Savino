<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "SDB Youth" torna a chiamarsi così.
 *
 * La migrazione precedente lo aveva allungato in "Savino Del Bene Youth"
 * interpretando la Brand & Digital Style Guide, che però negli usi scorretti
 * elenca le varianti con la d minuscola e "Savino Volley", non le sigle. La
 * redazione aveva chiesto il nome per esteso solo per il Title Sponsor, e
 * quello resta com'è.
 *
 * L'etichetta più lunga aveva anche una conseguenza pratica: la barra di
 * navigazione si dimensiona sullo spazio che le resta accanto ai loghi, e
 * centodue pixel in più mandavano il menu oltre il limite, facendolo
 * collassare nel pannello a scomparsa su qualunque schermo.
 */
return new class extends Migration
{
    private const URL_VIVAIO = '/youth/';

    private const SLUG_CATEGORIA = 'sdb-youth';

    public function up(): void
    {
        $this->rinomina(['it' => 'SDB Youth', 'en' => 'SDB Youth']);
    }

    public function down(): void
    {
        $this->rinomina(['it' => 'Savino Del Bene Youth', 'en' => 'Savino Del Bene Youth']);
    }

    /**
     * @param  array<string, string>  $etichetta
     */
    private function rinomina(array $etichetta): void
    {
        $json = json_encode($etichetta, JSON_UNESCAPED_UNICODE);

        // Per destinazione, non per identificativo: l'auto-increment cambia
        // fra ambienti e rinominerebbe la voce sbagliata.
        DB::table('menu_items')
            ->whereIn('url', [self::URL_VIVAIO, rtrim(self::URL_VIVAIO, '/')])
            ->update(['label' => $json]);

        // La categoria delle news segue il menu: due nomi diversi per la stessa
        // cosa sono peggio di una sigla.
        DB::table('categories')
            ->where('slug', self::SLUG_CATEGORIA)
            ->update(['name' => $json]);
    }
};

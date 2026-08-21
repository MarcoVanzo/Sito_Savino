<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Kit Gara" nel menu apriva direttamente le maglie Home.
 *
 * Le tre linee esistono già come categorie separate e piene di prodotti — la
 * Home sotto il nome "Kit Gara 25-26", la Away sotto "Kit Gara Away 25-26" e la
 * Champions sotto "Kit Champions" — ma erano tre reparti affiancati, e la voce
 * di menu puntava al primo. La migrazione precedente aveva creato tre
 * sottocategorie nuove e vuote: non serviva crearle, servivano quelle vere.
 *
 * Qui nasce il reparto "Kit Gara" e le tre linee gli diventano scaffali: la
 * pagina mostra tutte le maglie e in cima le linguette Home / Away /
 * Champions, che è il meccanismo già usato altrove.
 *
 * Gli slug non cambiano: i link già in giro devono continuare a funzionare.
 */
return new class extends Migration
{
    /** slug esistente => [nuovo nome, posizione] */
    private const LINEE = [
        'kit-gara-25-26' => ['Home', 1],
        'kit-gara-away-25-26' => ['Away', 2],
        'kit-champions' => ['Champions', 3],
    ];

    /** Le sottocategorie vuote create dalla migrazione precedente. */
    private const SEGNAPOSTO = ['kit-gara-home', 'kit-gara-away', 'kit-gara-champions'];

    public function up(): void
    {
        $linee = DB::table('product_categories')->whereIn('slug', array_keys(self::LINEE))->get();

        if ($linee->isEmpty()) {
            return;
        }

        $genitore = $this->reparto();

        foreach ($linee as $linea) {
            [$nome, $posizione] = self::LINEE[$linea->slug];

            DB::table('product_categories')->where('id', $linea->id)->update([
                'parent_id' => $genitore,
                'name' => json_encode(['it' => $nome, 'en' => $nome], JSON_UNESCAPED_UNICODE),
                'sort_order' => $posizione,
            ]);
        }

        $this->eliminaISegnaposto();
    }

    private function reparto(): int
    {
        $esistente = DB::table('product_categories')->where('slug', 'kit-gara')->first();

        if ($esistente) {
            return (int) $esistente->id;
        }

        return (int) DB::table('product_categories')->insertGetId([
            'name' => json_encode(['it' => 'Kit Gara', 'en' => 'Match Kit'], JSON_UNESCAPED_UNICODE),
            'slug' => 'kit-gara',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Si tolgono solo se sono rimaste vuote: se nel frattempo la redazione ci
     * ha messo dentro qualcosa, cancellarle porterebbe via il suo lavoro.
     */
    private function eliminaISegnaposto(): void
    {
        $vuote = DB::table('product_categories as pc')
            ->whereIn('pc.slug', self::SEGNAPOSTO)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('products')
                    ->whereColumn('products.product_category_id', 'pc.id');
            })
            ->pluck('pc.id');

        if ($vuote->isNotEmpty()) {
            DB::table('product_categories')->whereIn('id', $vuote)->delete();
        }
    }

    /**
     * Non reversibile: rimettere le tre linee una accanto all'altra
     * ripristinerebbe il difetto.
     */
    public function down(): void {}
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La pagina "societa" puntava al template `Public/Societa`, che non esiste fra i
 * componenti pubblici: il controller ripiegava su `Public/ContentPage` e nel
 * pannello la tendina del template risultava vuota. Il suo `content_data` era
 * per giunta l'ultimo rimasto nella vecchia struttura annidata
 * (`hero`, `storia`, `palazzetto`…), che nessuna pagina legge.
 *
 * Il testo della pagina resta dov'è: qui si allinea solo il template e si toglie
 * la struttura morta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pagina = DB::table('pages')->select('id')->where('slug', 'societa')->first();

        if (! $pagina) {
            return;
        }

        DB::table('pages')->where('id', $pagina->id)->update([
            'template' => 'Public/ContentPage',
            'content_data' => json_encode([], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function down(): void
    {
        // no-op: il template precedente non esiste.
    }
};

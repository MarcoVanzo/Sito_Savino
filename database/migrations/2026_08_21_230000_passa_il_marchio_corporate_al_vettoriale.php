<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Porta il marchio corporate al file vettoriale.
     *
     * Il PNG e' 3110 px di larghezza e nell'header viene disegnato a 200:
     * il browser lo riduce di quasi otto volte e le righe del cubo, che sono
     * novantatre tratti sottili, cadono sotto il passo dei pixel e si spezzano
     * in puntini. L'SVG viene rasterizzato direttamente alla misura giusta,
     * qualunque sia la densita' dello schermo, e le righe restano righe.
     */
    public function up(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->where('value', '/images/logo-corporate-left.png')
            ->update(['value' => '/images/logo-corporate-left.svg']);
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->where('value', '/images/logo-corporate-left.svg')
            ->update(['value' => '/images/logo-corporate-left.png']);
    }
};

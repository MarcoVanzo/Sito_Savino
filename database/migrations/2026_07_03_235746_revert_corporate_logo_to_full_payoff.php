<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ripristina il logo corporate completo con payoff (cubo + testo + sottotitolo).
     * La precedente migrazione aveva impostato solo l'icona cubo, ma la scelta
     * definitiva è il logo completo per una migliore riconoscibilità del brand.
     */
    public function up(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->update(['value' => '/images/logo-corporate.png']);
    }

    /**
     * Torna all'icona cubo senza testo.
     */
    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->update(['value' => '/images/logo-corporate-icon.png']);
    }
};

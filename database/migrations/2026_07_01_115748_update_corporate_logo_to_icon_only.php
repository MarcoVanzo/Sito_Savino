<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aggiorna il logo corporate da versione con payoff a solo icona (cubo SDB).
     * L'icona senza testo rende molto meglio nella navbar 85x85px.
     */
    public function up(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->where('value', '/images/logo-corporate.png')
            ->update(['value' => '/images/logo-corporate-icon.png']);
    }

    /**
     * Ripristina il logo corporate con payoff.
     */
    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->where('value', '/images/logo-corporate-icon.png')
            ->update(['value' => '/images/logo-corporate.png']);
    }
};

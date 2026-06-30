<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aggiorna il path del logo corporate: il vecchio Logo_Savino.jpeg
     * è stato sostituito con logo-corporate.png (versione completa con payoff).
     */
    public function up(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->where('value', '/images/Logo_Savino.jpeg')
            ->update(['value' => '/images/logo-corporate.png']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->where('value', '/images/logo-corporate.png')
            ->update(['value' => '/images/Logo_Savino.jpeg']);
    }
};

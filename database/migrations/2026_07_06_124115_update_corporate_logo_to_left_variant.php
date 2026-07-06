<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Aggiorna il logo corporate alla variante con il cubo a sinistra,
     * come da brand book ufficiale Savino Del Bene (layout orizzontale).
     */
    public function up(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->update(['value' => '/images/logo-corporate-left.png']);
    }

    /**
     * Ripristina il logo corporate con cubo sopra.
     */
    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'corporate_logo')
            ->update(['value' => '/images/logo-corporate.png']);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La foto della tendina Ticketing mostrava il tavolo degli ufficiali di gara.
 *
 * Lo scatto è largo 720 e il riquadro nel mega-menu è alto e stretto (due
 * quinti della tendina): ritagliando dal centro l'atleta al servizio, che sta
 * sul bordo sinistro, restava fuori. La colonna per scegliere il punto di
 * messa a fuoco l'ha introdotta la migrazione precedente; qui si usa.
 *
 * Vale solo finché la foto è quella importata: se la redazione ne carica
 * un'altra, il punto si cambia dal pannello (Menu → voce → Posizione foto).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')
            ->whereIn('url', ['/ticketing', '/ticketing/'])
            ->whereNull('parent_id')
            ->update(['menu_image_position' => 'left']);
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->whereIn('url', ['/ticketing', '/ticketing/'])
            ->whereNull('parent_id')
            ->update(['menu_image_position' => 'center']);
    }
};

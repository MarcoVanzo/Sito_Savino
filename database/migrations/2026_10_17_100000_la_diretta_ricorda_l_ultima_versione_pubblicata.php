<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Con Spaces giù la regia salvava lo stato ma il JSON restava vecchio, e il
 * pulsante appena premuto spariva: niente lo ripubblicava. Ricordare l'ultima
 * versione arrivata sul JSON permette di riprovare (comando al minuto e
 * pulsante «Ripubblica»). Le righe esistenti si danno per pubblicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('live_sessions', 'versione_pubblicata')) {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->unsignedInteger('versione_pubblicata')->default(0)->after('versione');
            });

            DB::table('live_sessions')->update(['versione_pubblicata' => DB::raw('versione')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('live_sessions', 'versione_pubblicata')) {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->dropColumn('versione_pubblicata');
            });
        }
    }
};

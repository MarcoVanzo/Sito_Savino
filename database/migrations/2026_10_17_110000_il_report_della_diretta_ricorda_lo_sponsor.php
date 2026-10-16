<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Il report filtrato per sponsor ritrovava le righe delle grafiche
 * cancellate confrontando il nome copiato con quello attuale: rinominato lo
 * sponsor, sparivano. Si copia anche l'id, senza vincolo: deve restare anche
 * quando lo sponsor non c'è più. Le righe già staccate dalla grafica non si
 * ricollegano per nome (due sponsor omonimi): al 17/10/2026 non ce n'erano.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('live_ad_stats', 'sponsor_id')) {
            Schema::table('live_ad_stats', function (Blueprint $table) {
                $table->unsignedBigInteger('sponsor_id')->nullable()->after('live_creative_id');
            });
        }

        DB::table('live_ad_stats')
            ->whereNull('live_ad_stats.sponsor_id')
            ->join('live_creatives', 'live_creatives.id', '=', 'live_ad_stats.live_creative_id')
            ->update(['live_ad_stats.sponsor_id' => DB::raw('live_creatives.sponsor_id')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('live_ad_stats', 'sponsor_id')) {
            Schema::table('live_ad_stats', function (Blueprint $table) {
                $table->dropColumn('sponsor_id');
            });
        }
    }
};

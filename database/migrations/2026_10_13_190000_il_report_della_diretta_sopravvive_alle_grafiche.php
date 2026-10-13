<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Il report della diretta non sparisce con la grafica o con lo sponsor.
 *
 * I contatori erano legati alla creatività in cascata: la comunicazione
 * toglie uno spot finita la campagna, e i numeri da mandare allo sponsor se
 * ne andavano con lui. Ora la riga resta (creatività a `null`) con il nome
 * della grafica e dello sponsor copiati prima della cancellazione
 * (`LiveCreative::booted()`); qui si copiano per le righe che ci sono già.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('live_ad_stats', 'creativita_nome')) {
            Schema::table('live_ad_stats', function (Blueprint $table) {
                $table->string('sponsor_nome')->nullable()->after('live_creative_id');
                $table->string('creativita_nome')->nullable()->after('sponsor_nome');
            });
        }

        Schema::table('live_ad_stats', function (Blueprint $table) {
            $table->dropForeign(['live_creative_id']);
        });

        Schema::table('live_ad_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('live_creative_id')->nullable()->change();
            $table->foreign('live_creative_id')->references('id')->on('live_creatives')->nullOnDelete();
        });

        DB::table('live_ad_stats')
            ->join('live_creatives', 'live_creatives.id', '=', 'live_ad_stats.live_creative_id')
            ->join('sponsors', 'sponsors.id', '=', 'live_creatives.sponsor_id')
            ->update([
                'live_ad_stats.sponsor_nome' => DB::raw('sponsors.name'),
                'live_ad_stats.creativita_nome' => DB::raw('live_creatives.nome'),
            ]);
    }

    /**
     * Nessun ritorno: con righe già staccate dalla creatività la colonna non
     * può tornare obbligatoria, e i nomi copiati sono l'unica traccia rimasta.
     */
    public function down(): void {}
};

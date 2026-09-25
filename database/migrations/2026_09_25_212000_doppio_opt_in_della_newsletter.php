<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La newsletter iscriveva chiunque scrivesse un indirizzo nel modulo, senza
 * chiedere al proprietario della casella: il consenso non era dimostrabile, e
 * bastava l'email di un altro per metterlo in lista. Da qui l'iscrizione vale
 * solo dopo il click sul link di conferma (`confermato_il`), ed è solo allora
 * che il contatto arriva ad ActiveCampaign.
 *
 * Le righe già in archivio (tre, a settembre 2026, tutte del luglio 2026 e due
 * già sincronizzate) si danno per confermate alla data d'iscrizione: sono
 * entrate con le regole di allora, e toglierle da ActiveCampaign adesso non
 * produrrebbe una prova del consenso che non c'è. Se ne scrive qui la ragione
 * perché la data non venga presa per una conferma vera.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('newsletter_subscribers', 'confermato_il')) {
            Schema::table('newsletter_subscribers', function (Blueprint $table) {
                $table->timestamp('confermato_il')->nullable()->after('subscribed_at');
            });
        }

        DB::table('newsletter_subscribers')
            ->whereNull('confermato_il')
            ->where('created_at', '<', '2026-09-26 00:00:00')
            ->update(['confermato_il' => DB::raw('COALESCE(subscribed_at, created_at)')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('newsletter_subscribers', 'confermato_il')) {
            Schema::table('newsletter_subscribers', function (Blueprint $table) {
                $table->dropColumn('confermato_il');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Il consenso di marketing del banner, fotografato sull'ordine al checkout:
 * la Conversions API di Meta (InviaAcquistoAMeta) manda l'acquisto dal server
 * solo per gli ordini fatti con quel consenso. `consenso_cookie_riferimento`
 * e' la scelta del registro (`consensi_cookie.riferimento`) che lo prova.
 *
 * `_fbp`, `_fbc` e lo user agent si scrivono solo con il consenso: servono a
 * Meta per attribuire l'acquisto all'inserzione.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'consenso_marketing')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('consenso_marketing')->default(false)->after('condizioni_impronta');
            $table->string('consenso_cookie_riferimento', 36)->nullable()->after('consenso_marketing');
            $table->string('meta_fbp')->nullable()->after('consenso_cookie_riferimento');
            $table->string('meta_fbc')->nullable()->after('meta_fbp');
            $table->text('meta_user_agent')->nullable()->after('meta_fbc');
            $table->timestamp('meta_acquisto_inviato_at')->nullable()->after('meta_user_agent');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'consenso_marketing')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'consenso_marketing', 'consenso_cookie_riferimento',
                'meta_fbp', 'meta_fbc', 'meta_user_agent', 'meta_acquisto_inviato_at',
            ]);
        });
    }
};

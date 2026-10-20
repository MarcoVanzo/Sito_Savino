<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dal 20/10/2026 la registrazione allo shop non chiede più di spuntare
 * «Accetto la Privacy Policy»: l'informativa si dà, non si accetta (artt. 13 e
 * 6 §1 b GDPR, l'account serve a eseguire il contratto). Resta la prova di
 * quale versione è stata mostrata: la data della Privacy Policy in vigore
 * (`TestiDelleInformative::versione('privacy-policy')`).
 *
 * Nullable: gli account nati prima non hanno registrato niente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'informativa_privacy_versione')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('informativa_privacy_versione', 10)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'informativa_privacy_versione')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('informativa_privacy_versione');
            });
        }
    }
};

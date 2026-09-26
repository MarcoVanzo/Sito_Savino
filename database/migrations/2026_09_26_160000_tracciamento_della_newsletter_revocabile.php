<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linee guida del Garante del 17/04/2026 sui pixel di tracciamento nelle
 * email (provv. 284, adeguamento entro il 29/10/2026): chi e' iscritto alla
 * newsletter deve poter revocare il solo tracciamento — aperture e clic —
 * continuando a ricevere le email. `tracciamento_revocato_il` e' quella
 * scelta, con la data; ActiveCampaign la riceve come tag
 * (`SyncNewsletterToActiveCampaign`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->timestamp('tracciamento_revocato_il')->nullable()->after('confermato_il');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn('tracciamento_revocato_il');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registra la lingua scelta da chi compra, per spedire le email nella sua lingua.
 *
 * Le mail transazionali sono `ShouldQueue`: partono da un worker, quando la
 * richiesta HTTP che ha creato l'ordine non esiste più e `app()->getLocale()`
 * è tornato al default italiano. Senza questo dato un cliente che compra da
 * /en riceverebbe la conferma d'ordine in italiano, che è esattamente quello
 * che succedeva finora.
 *
 * Il default è `it` sia per le righe esistenti sia per quelle nuove: è la
 * lingua in cui quegli ordini sono stati effettivamente fatti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('locale', 5)->default('it')->after('order_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('it')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};

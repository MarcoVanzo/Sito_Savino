<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quanto e' stato restituito al cliente su un ordine, cumulato.
 *
 * Il webhook trattava ogni rimborso come totale: bastava restituire le spese
 * di spedizione perche' l'ordine passasse a Rimborsato, la merce tornasse in
 * giacenza (mentre era ancora dal cliente) e partisse l'email di rimborso
 * completo. Ora solo il rimborso totale cambia stato; quello parziale lascia
 * l'ordine com'e' e registra qui l'importo, letto dal gateway come totale
 * cumulato (idempotente: lo stesso evento ripetuto scrive la stessa cifra).
 *
 * Additiva e nullable: nullo vale "nessun rimborso registrato". A guardia
 * perche' le migrazioni girano a ogni avvio del container.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'refunded_amount')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('refunded_amount', 10, 2)->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'refunded_amount')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};

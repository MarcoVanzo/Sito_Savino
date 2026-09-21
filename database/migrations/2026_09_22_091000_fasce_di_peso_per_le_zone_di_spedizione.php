<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * La spedizione costa anche in base al peso del collo.
 *
 * Le zone avevano una tariffa sola per tutto il paese: in Italia sono 7,50 €
 * entro i 5 kg e poi si sale per fasce, e senza questa distinzione una scatola
 * di dieci maglie viaggiava al prezzo di una sciarpa.
 *
 * Le fasce stanno in una colonna JSON e non in una tabella a parte perché le
 * zone attive vengono messe in cache come semplici attributi
 * (ShippingZone::getCachedZones): una relazione non sopravviverebbe a quel
 * giro e verrebbe ricaricata a ogni richiesta.
 *
 * Ogni fascia è `{"max_weight": 5, "rate": 7.50}`, ordinata per peso
 * crescente; l'ultima può avere `max_weight` vuoto e vale "da lì in su".
 * Nessuna fascia significa "vale la tariffa base", cioè il comportamento di
 * prima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->json('weight_rates')->nullable()->after('flat_rate');
        });

        Cache::forget('shipping_zones_active');
    }

    public function down(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->dropColumn('weight_rates');
        });

        Cache::forget('shipping_zones_active');
    }
};

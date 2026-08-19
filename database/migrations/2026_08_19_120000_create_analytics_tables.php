<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics del sito (Google Analytics 4).
 *
 * `analytics_sites` esiste perché i siti da misurare sono più di uno e possono
 * cambiare: la property GA4 si sostituisce (si sbaglia a crearla, si rifà
 * l'account) mentre la serie storica deve restare attaccata al *sito*, non
 * all'identificativo Google. Per questo `web_analytics_daily` punta al sito e
 * tiene la property solo come annotazione di quale fonte ha prodotto la riga.
 *
 * La serie giornaliera si salva in casa a ogni lettura: è quello che permette
 * confronti anno su anno anche se la property viene chiusa, e regge la pagina
 * quando la quota della Data API è esaurita. `is_final` distingue i giorni
 * ormai definitivi da quelli che GA4 può ancora rielaborare (48 ore).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Numerico, ma resta stringa: è un identificativo, non una quantità,
            // e conserva eventuali zeri iniziali senza sorprese di cast.
            $table->string('property_id', 20);
            $table->string('url')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique('property_id', 'uq_analytics_sites_property');
        });

        Schema::create('web_analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analytics_site_id')->constrained()->cascadeOnDelete();
            $table->string('property_id', 20);
            $table->date('day');
            $table->unsignedInteger('active_users')->default(0);
            $table->unsignedInteger('new_users')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('engaged_sessions')->default(0);
            $table->unsignedInteger('engagement_seconds')->default(0);
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->unique(['analytics_site_id', 'day'], 'uq_web_analytics_daily');
            $table->index('day', 'idx_web_analytics_daily_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_analytics_daily');
        Schema::dropIfExists('analytics_sites');
    }
};

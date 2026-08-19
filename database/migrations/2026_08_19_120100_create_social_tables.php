<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Social analytics (Meta: Instagram + Facebook).
 *
 * Gli account collegati sono più di uno — la prima squadra e il settore
 * giovanile hanno Pagina e profilo Instagram distinti — quindi il collegamento
 * non è un'impostazione singola ma una riga per account. Il token è cifrato a
 * riposo (cast `encrypted` sul model): dà accesso in lettura agli insight di
 * una Pagina reale, non è un identificativo qualunque.
 *
 * `social_insights_daily` esiste per un vincolo della Graph API, non per fare
 * cache: le metriche di account Instagram arrivano o come `time_series` (solo
 * `reach` e `follower_count`) o come `total_value`, che restituisce UN numero
 * per l'intero intervallo richiesto. Per avere il grafico giorno per giorno
 * serve una chiamata per giorno: si fa una volta sola e si conserva. `is_final`
 * marca i giorni che Meta non ritocca più (48 ore).
 *
 * `social_oauth_states` regge il giro OAuth: la callback torna senza sessione
 * utente affidabile, e lo state deve essere verificabile e a scadenza breve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            // Etichetta redazionale ("Prima squadra", "Settore giovanile"):
            // il nome della Pagina arriva da Meta e può cambiare senza preavviso.
            $table->string('name');
            $table->string('page_id', 64)->nullable();
            $table->string('page_name')->nullable();
            $table->string('ig_account_id', 64)->nullable();
            $table->string('ig_username')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            // Una Pagina si collega una volta sola: un secondo collegamento
            // aggiorna la riga esistente invece di duplicare la serie storica.
            $table->unique('page_id', 'uq_social_accounts_page');
        });

        Schema::create('social_oauth_states', function (Blueprint $table) {
            $table->string('token', 64)->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at', 'idx_social_oauth_states_expires');
        });

        Schema::create('social_insights_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('ig_account_id', 64);
            $table->date('day');
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('views')->default(0);
            // Firmato: i follower possono calare, e Meta a volte corregge il dato
            // all'indietro. Con UNSIGNED l'inserimento fallirebbe.
            $table->integer('follower_count')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('saves')->default(0);
            $table->unsignedInteger('reposts')->default(0);
            $table->unsignedInteger('replies')->default(0);
            $table->unsignedInteger('total_interactions')->default(0);
            $table->unsignedInteger('accounts_engaged')->default(0);
            $table->unsignedInteger('profile_links_taps')->default(0);
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->unique(['social_account_id', 'day'], 'uq_social_insights_daily');
            $table->index('day', 'idx_social_insights_daily_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_insights_daily');
        Schema::dropIfExists('social_oauth_states');
        Schema::dropIfExists('social_accounts');
    }
};

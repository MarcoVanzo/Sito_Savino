<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I redirect del vecchio sito decisi a mano, e i vecchi indirizzi ancora
 * richiesti che nessuno ha mappato.
 *
 * `redirects` vince sulle rotte di `legacy.php` e sul permalink automatico
 * delle notizie (`RedirectGestiti`). `redirect_mancanti` tiene una riga per
 * percorso con un contatore: è la lista da cui la redazione crea i redirect,
 * e si pota da sola (`IndirizzoDaMappare::prunable`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path', 2048)->nullable();
            // `CodiceDelRedirect`: 301 o 410.
            $table->string('status_code', 3)->default('301');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('redirect_mancanti', function (Blueprint $table) {
            $table->id();
            $table->string('percorso')->unique();
            // `404` (nessuna rotta o contenuto) o `legacy` (rotta generica di
            // legacy.php, che porta su una pagina d'archivio).
            $table->string('origine', 16);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirect_mancanti');
        Schema::dropIfExists('redirects');
    }
};

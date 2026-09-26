<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelle dello store di cache `persistente` (config/cache.php): lo stato
 * degli avvisi, che deve sopravvivere al `cache:clear` di start.sh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cache_persistente')) {
            Schema::create('cache_persistente', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });
        }

        if (! Schema::hasTable('cache_persistente_locks')) {
            Schema::create('cache_persistente_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->bigInteger('expiration')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_persistente');
        Schema::dropIfExists('cache_persistente_locks');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memoria della traduzione automatica delle notizie: per ogni campo
 * l'impronta dell'italiano tradotto e dell'inglese scritto dal job. Serve a
 * riconoscere un inglese corretto a mano dalla redazione, che non si tocca più.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->json('traduzioni_automatiche')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('traduzioni_automatiche');
        });
    }
};

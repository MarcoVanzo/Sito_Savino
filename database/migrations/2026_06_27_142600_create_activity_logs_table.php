<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Chi ha eseguito l'azione (nullable per azioni di sistema/CLI)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Tipo di azione eseguita
            $table->string('action', 20); // created, updated, deleted, restored, force_deleted

            // Modello coinvolto (tipo polimorfico)
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            // Label leggibile del record (es. titolo del post)
            // Salvata qui perché il record potrebbe essere cancellato
            $table->string('model_label')->nullable();

            // Diff delle modifiche: { "old": {...}, "new": {...} }
            $table->json('changes')->nullable();

            // Contesto della richiesta
            $table->string('ip_address', 45)->nullable(); // supporto IPv6
            $table->string('user_agent')->nullable();

            // Solo created_at — i log sono immutabili
            $table->timestamp('created_at')->useCurrent();

            // Indici per query frequenti
            $table->index(['model_type', 'model_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

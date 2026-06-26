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
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            
            $table->integer('points')->default(0);
            $table->integer('blocks')->default(0);
            $table->integer('aces')->default(0);
            $table->integer('attacks')->default(0);
            $table->integer('receptions')->default(0);
            
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['player_id', 'season_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};

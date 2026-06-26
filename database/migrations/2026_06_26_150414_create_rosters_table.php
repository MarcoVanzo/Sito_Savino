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
        Schema::create('rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            
            $table->integer('jersey_number')->nullable();
            $table->string('role')->nullable(); // 'Schiacciatrice', 'Palleggiatrice'
            $table->integer('height_cm')->nullable();
            $table->boolean('is_captain')->default(false);
            
            $table->string('official_photo_url')->nullable();
            $table->string('action_photo_url')->nullable();
            $table->text('bio')->nullable(); // Captain bio
            
            $table->timestamps();
            
            $table->unique(['player_id', 'team_id', 'season_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rosters');
    }
};

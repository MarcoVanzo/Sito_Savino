<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabella pivot polimorfica gallery_image_person
     * e migra i dati dalla vecchia gallery_image_player.
     */
    public function up(): void
    {
        Schema::create('gallery_image_person', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_image_id')->constrained()->cascadeOnDelete();
            $table->morphs('person'); // person_type + person_id
            $table->decimal('confidence_score', 5, 2)->nullable()->comment('Percentuale di sicurezza AI (0-100)');
            $table->timestamps();

            $table->unique(['gallery_image_id', 'person_type', 'person_id'], 'gip_unique');
        });

        // Migra i dati dalla vecchia tabella pivot
        if (Schema::hasTable('gallery_image_player')) {
            DB::table('gallery_image_player')->orderBy('id')->chunk(500, function ($rows) {
                $inserts = [];
                foreach ($rows as $row) {
                    $inserts[] = [
                        'gallery_image_id' => $row->gallery_image_id,
                        'person_type' => 'App\\Models\\Player',
                        'person_id' => $row->player_id,
                        'confidence_score' => $row->confidence_score,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }
                if (! empty($inserts)) {
                    DB::table('gallery_image_person')->insert($inserts);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_image_person');
    }
};

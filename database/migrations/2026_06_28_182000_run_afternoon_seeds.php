<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // In produzione questa migrazione è già passata il 28/06/2026: da
        // allora la incontra solo un database nuovo, dove i seeder di oggi
        // girerebbero su uno schema di giugno. Il primo salvataggio di una
        // foto accoda la ricostruzione della gallery, che con la coda sync
        // cerca `gallery_images` prima che esista e interrompe la semina a
        // metà. Su un database nuovo si semina con `migrate --seed`, a schema
        // completo.
        if (app()->environment('testing') || ! Schema::hasTable('gallery_images')) {
            return;
        }

        try {
            // Run the DatabaseSeeder which includes the new StaffAndDirigenzaSeeder and Roster2026Seeder
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\DatabaseSeeder',
                '--force' => true,
            ]);
            Log::info('DatabaseSeeder executed successfully via migration.', ['output' => Artisan::output()]);

            // Run the custom command to seed images to the media library
            Artisan::call('app:seed-menu-images');
            Log::info('app:seed-menu-images executed successfully via migration.', ['output' => Artisan::output()]);

            // Clear cache to ensure frontend fetches the fresh data
            Artisan::call('optimize:clear');
        } catch (Exception $e) {
            Log::error('Error running seeds from migration: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration for data seeds
    }
};

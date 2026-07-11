<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        // Run CmsPagesSeeder to update Storia, Safeguarding and Palazzetto with pre-populated content_data
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\CmsPagesSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration is necessary since seeders populate standard production defaults.
    }
};

<?php

use App\Models\MenuItem;
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

        // 1. Run CmsPagesSeeder to populate the pages and their contents
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\CmsPagesSeeder', '--force' => true]);

        // 2. Run MenuItemSeeder to recreate menu items with correct public paths
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\MenuItemSeeder', '--force' => true]);

        // 3. Re-run the English translations for menu items to ensure multilingual support is pristine
        $translationMigrationPath = database_path('migrations/2026_07_05_125535_add_english_translations_to_menu_items.php');
        if (file_exists($translationMigrationPath)) {
            $migration = require $translationMigrationPath;
            $migration->up();
        }

        // 4. Forcefully clear application cache for menu items
        MenuItem::clearCache();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration is necessary since seeders populate standard production defaults.
    }
};

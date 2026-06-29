<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix rosters role (lowercase to match PlayerPosition backing values)
        DB::statement('UPDATE rosters SET role = LOWER(role) WHERE role IS NOT NULL');

        // Fix pages status (lowercase to match PostStatus backing values)
        DB::statement('UPDATE pages SET status = LOWER(status) WHERE status IS NOT NULL');
        DB::table('pages')->where('status', 'published')->update(['status' => 'publish']);

        // Fix users role (lowercase to match UserRole backing values)
        DB::statement('UPDATE users SET role = LOWER(role) WHERE role IS NOT NULL');

        // Clear the cache to prevent cached models with invalid enum states from crashing the app
        Cache::flush();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non è possibile (o necessario) revertire.
    }
};

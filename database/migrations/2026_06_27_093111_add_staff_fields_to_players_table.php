<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->boolean('is_staff')->default(false)->after('lega_volley_id');
            $table->string('staff_role')->nullable()->after('is_staff');
            $table->string('photo_url')->nullable()->after('staff_role');
            $table->unsignedInteger('sort_order')->default(0)->after('photo_url');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['is_staff', 'staff_role', 'photo_url', 'sort_order']);
        });
    }
};

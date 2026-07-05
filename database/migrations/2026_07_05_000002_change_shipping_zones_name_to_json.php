<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert existing string values to JSON
        DB::table('shipping_zones')->get()->each(function ($zone) {
            $current = $zone->name;
            if (json_decode($current) !== null) return;
            DB::table('shipping_zones')
                ->where('id', $zone->id)
                ->update(['name' => json_encode(['it' => $current])]);
        });

        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->json('name')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->string('name', 100)->change();
        });
    }
};

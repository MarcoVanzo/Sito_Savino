<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert existing string values to JSON for each translatable column
        DB::table('auctions')->get()->each(function ($auction) {
            $updates = [];

            foreach (['title', 'description', 'charity_description'] as $column) {
                $current = $auction->{$column};
                if ($current === null) continue;
                if (json_decode($current) !== null) continue;
                $updates[$column] = json_encode(['it' => $current]);
            }

            if (!empty($updates)) {
                DB::table('auctions')
                    ->where('id', $auction->id)
                    ->update($updates);
            }
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->json('title')->change();
            $table->json('description')->nullable()->change();
            $table->json('charity_description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('title', 255)->change();
            $table->text('description')->nullable()->change();
            $table->text('charity_description')->nullable()->change();
        });
    }
};

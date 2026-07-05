<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->timestamp('used_at')->useCurrent()->change();
        });
        Schema::table('bids', function (Blueprint $table) {
            $table->timestamp('placed_at')->useCurrent()->change();
        });
        Schema::table('shop_events', function (Blueprint $table) {
            $table->timestamp('created_at')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->timestamp('used_at')->nullable(false)->default(null)->change();
        });
        Schema::table('bids', function (Blueprint $table) {
            $table->timestamp('placed_at')->nullable(false)->default(null)->change();
        });
        Schema::table('shop_events', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable(false)->default(null)->change();
        });
    }
};

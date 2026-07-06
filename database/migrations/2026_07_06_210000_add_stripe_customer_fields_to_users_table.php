<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('address');
            $table->boolean('has_verified_payment_method')->default(false)->after('stripe_customer_id');
            $table->timestamp('payment_method_verified_at')->nullable()->after('has_verified_payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_customer_id',
                'has_verified_payment_method',
                'payment_method_verified_at',
            ]);
        });
    }
};

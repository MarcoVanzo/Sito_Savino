<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts shipping_address and billing_address from TEXT to JSON,
     * wrapping any existing plain-text values in a {raw_address: "..."} JSON object
     * for backward compatibility.
     */
    public function up(): void
    {
        // 1. Migrate existing plain text shipping_address values to JSON
        DB::table('orders')->whereNotNull('shipping_address')->orderBy('id')->each(function ($order) {
            $decoded = json_decode($order->shipping_address, true);
            if ($decoded === null && $order->shipping_address !== 'null') {
                DB::table('orders')->where('id', $order->id)->update([
                    'shipping_address' => json_encode(['raw_address' => $order->shipping_address]),
                ]);
            }
        });

        // 2. Migrate existing plain text billing_address values to JSON
        DB::table('orders')->whereNotNull('billing_address')->orderBy('id')->each(function ($order) {
            $decoded = json_decode($order->billing_address, true);
            if ($decoded === null && $order->billing_address !== 'null') {
                DB::table('orders')->where('id', $order->id)->update([
                    'billing_address' => json_encode(['raw_address' => $order->billing_address]),
                ]);
            }
        });

        // 3. Change column type from TEXT to JSON using raw SQL
        //    MySQL 8.4 doesn't support direct TEXT→JSON change via Schema builder easily
        DB::statement('ALTER TABLE orders MODIFY shipping_address JSON NULL, MODIFY billing_address JSON NULL;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert JSON columns back to TEXT
        DB::statement('ALTER TABLE orders MODIFY shipping_address TEXT NULL, MODIFY billing_address TEXT NULL;');
    }
};

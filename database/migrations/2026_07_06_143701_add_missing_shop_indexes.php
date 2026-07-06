<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Indici mancanti per lo shop
|--------------------------------------------------------------------------
|
| Aggiunge indici per migliorare le performance delle query più frequenti:
| - orders.guest_email: ricerca ordini ospiti per email
| - products (is_active, type): filtro catalogo prodotti attivi per tipo
| - products.sort_order: ordinamento catalogo
| - coupon_usages.user_id: lookup utilizzi coupon per utente
| - coupon_usages.guest_email: lookup utilizzi coupon per ospite
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('guest_email', 'orders_guest_email_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'type'], 'products_is_active_type_index');
            $table->index('sort_order', 'products_sort_order_index');
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->index('user_id', 'coupon_usages_user_id_index');
            $table->index('guest_email', 'coupon_usages_guest_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_guest_email_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_is_active_type_index');
            $table->dropIndex('products_sort_order_index');
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropIndex('coupon_usages_user_id_index');
            $table->dropIndex('coupon_usages_guest_email_index');
        });
    }
};

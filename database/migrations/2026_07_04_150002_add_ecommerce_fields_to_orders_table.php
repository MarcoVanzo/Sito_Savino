<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Aggiunta campi e-commerce alla tabella orders
|--------------------------------------------------------------------------
|
| Aggiunge supporto completo per:
| - Identificazione ordine (order_number, order_token)
| - Acquisto come ospite (guest_email, guest_name, guest_phone)
| - Paese e informazioni di spedizione
| - Gateway di pagamento generico (sostituzione di stripe_payment_id)
| - Tracking spedizione
| - Coupon e sconti
| - Note e privacy
|
| Migra i dati esistenti da stripe_payment_id a payment_id
| prima di rimuovere la colonna legacy.
|
*/

return new class extends Migration
{
    /**
     * Esegui la migrazione.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Identificazione univoca dell'ordine
            $table->string('order_number', 30)->nullable()->unique()->after('id');
            $table->char('order_token', 36)->nullable()->unique()->after('order_number');

            // Dati ospite per acquisti senza registrazione
            $table->string('guest_email', 255)->nullable()->after('user_id');
            $table->string('guest_name', 255)->nullable()->after('guest_email');
            $table->string('guest_phone', 30)->nullable()->after('guest_name');

            // Paese di destinazione (codice ISO 2 lettere)
            $table->string('country', 2)->nullable()->after('billing_address');

            // Gateway di pagamento generico (stripe, paypal, ecc.)
            $table->string('payment_gateway', 20)->nullable()->after('country');
            $table->string('payment_id', 255)->nullable()->unique()->after('payment_gateway');
            $table->timestamp('paid_at')->nullable()->after('payment_id');

            // Informazioni di spedizione e tracking
            $table->timestamp('shipped_at')->nullable()->after('paid_at');
            $table->string('tracking_number', 255)->nullable()->after('shipped_at');
            $table->string('tracking_url', 500)->nullable()->after('tracking_number');
            $table->decimal('shipping_cost', 10, 2)->default(0)->after('tracking_url');

            // Coupon e sconti (FK aggiunta nella migrazione 11)
            $table->unsignedBigInteger('coupon_id')->nullable()->after('shipping_cost');
            $table->decimal('coupon_discount', 10, 2)->default(0)->after('coupon_id');

            // Note e consenso privacy
            $table->text('notes')->nullable()->after('coupon_discount');
            $table->timestamp('privacy_accepted_at')->nullable()->after('notes');
        });

        // Migrazione dati: copio stripe_payment_id nei nuovi campi
        if (Schema::hasColumn('orders', 'stripe_payment_id')) {
            // Imposta payment_gateway='stripe' e copia l'ID per i record con valore non nullo
            DB::table('orders')
                ->whereNotNull('stripe_payment_id')
                ->update([
                    'payment_id'      => DB::raw('stripe_payment_id'),
                    'payment_gateway' => 'stripe',
                ]);

            // Rimuovi la colonna legacy
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('stripe_payment_id');
            });
        }

        // Indici compositi per query frequenti
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
            $table->index(['payment_gateway', 'created_at'], 'orders_payment_gateway_created_at_index');
        });
    }

    /**
     * Annulla la migrazione.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Rimuovi gli indici compositi
            $table->dropIndex('orders_status_created_at_index');
            $table->dropIndex('orders_payment_gateway_created_at_index');
        });

        // Ripristina la colonna stripe_payment_id con i dati migrati
        Schema::table('orders', function (Blueprint $table) {
            $table->string('stripe_payment_id')->nullable()->after('billing_address');
        });

        // Ripristina i dati da payment_id a stripe_payment_id
        DB::table('orders')
            ->where('payment_gateway', 'stripe')
            ->whereNotNull('payment_id')
            ->update([
                'stripe_payment_id' => DB::raw('payment_id'),
            ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_number',
                'order_token',
                'guest_email',
                'guest_name',
                'guest_phone',
                'country',
                'payment_gateway',
                'payment_id',
                'paid_at',
                'shipped_at',
                'tracking_number',
                'tracking_url',
                'shipping_cost',
                'coupon_id',
                'coupon_discount',
                'notes',
                'privacy_accepted_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * L'ordine nato da un'asta veniva legato all'asta riusando il token: si creava
 * con `order_token = auctions.winner_checkout_token` e lo si ritrovava con una
 * uguaglianza fra le due colonne.
 *
 * Ma `order_token` è un capability token pubblico — con quello si apre il
 * dettaglio ordine da guest e si scarica la ricevuta PDF (indirizzo, dati
 * fiscali) senza autenticazione — mentre `winner_checkout_token` viaggia via
 * mail e sta nell'URL del checkout. Riusarlo faceva sì che chiunque venisse in
 * possesso dell'uno avesse anche l'altro.
 *
 * Qui il legame diventa una foreign key esplicita e i due token tornano
 * indipendenti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('auction_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            // Sostituisce la protezione che prima dava l'indice unico su
            // order_token: un solo ordine per (asta, utente), così due submit
            // ravvicinati non creano due ordini per lo stesso vincitore.
            // MySQL ammette più righe con auction_id NULL: gli ordini normali
            // non sono toccati.
            $table->unique(['auction_id', 'user_id'], 'orders_auction_user_unique');
        });

        // Backfill del legame esistente, prima di rigenerare i token.
        DB::table('orders')
            ->join('auctions', 'auctions.winner_checkout_token', '=', 'orders.order_token')
            ->update(['orders.auction_id' => DB::raw('auctions.id')]);

        // Rigenera l'order_token degli ordini d'asta: quello attuale coincide
        // con il token di checkout già circolato via mail.
        DB::table('orders')
            ->whereNotNull('auction_id')
            ->select('id')
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['order_token' => Str::uuid()->toString()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_auction_user_unique');
            $table->dropConstrainedForeignId('auction_id');
        });
    }
};

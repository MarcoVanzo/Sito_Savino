<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il checkout rimanda alla Privacy Policy, che per gli acquisti aveva una riga
 * sola: concludere l'ordine, recesso, obblighi fiscali. Il sito però fa di
 * più con i dati dell'ordine: avvisa per email gli addetti dello shop, tiene
 * la prova delle condizioni accettate, li usa per rimborsi e contestazioni, e
 * contava le visite allo shop con indirizzo IP, sessione e utente, senza
 * dirlo e senza scadenza.
 *
 * Due cose: la Privacy Policy si riscrive a guardia (solo dove resta la
 * versione del 26/09, riconosciuta dalle `firme`), con una sezione
 * `#acquisti` a cui punta il checkout; e dagli eventi dello shop si tolgono
 * IP e sessione, che nessuna statistica legge e che da ora non si scrivono
 * più (TrackShopPageView, CheckoutController). Dalle visite si toglie anche
 * l'utente: si conta la pagina, non chi la guarda.
 */
return new class extends Migration
{
    public function up(): void
    {
        TestiDelleInformative::riscriviDoveNonToccata();

        DB::table('shop_events')
            ->where(fn ($q) => $q->whereNotNull('ip_address')->orWhereNotNull('session_id'))
            ->update(['ip_address' => null, 'session_id' => null]);

        DB::table('shop_events')
            ->where('event_type', 'view')
            ->whereNotNull('user_id')
            ->update(['user_id' => null]);
    }

    /**
     * Non si annulla: i dati tolti non servivano, e il testo di prima non
     * diceva quello che il sito fa.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

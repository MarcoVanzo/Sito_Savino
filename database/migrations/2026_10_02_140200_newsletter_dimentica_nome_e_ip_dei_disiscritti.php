<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chi si è disiscritto dalla newsletter non ha più bisogno che teniamo nome e
 * IP: della sua iscrizione restano email, data di richiesta, di conferma e di
 * revoca, come prova per ventiquattro mesi (NewsletterSubscriber::prunable).
 * Da oggi lo fa `NewsletterSubscriber::unsubscribe()`; questa migrazione
 * applica la regola alle righe già disiscritte.
 *
 * Restano fuori le righe con una richiesta d'iscrizione più recente della
 * disiscrizione (`subscribed_at` dopo `unsubscribed_at`): nome e IP sono di
 * quella richiesta, che aspetta la conferma.
 *
 * Idempotente: a ogni avvio non trova più niente da azzerare.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('newsletter_subscribers')
            ->whereNotNull('unsubscribed_at')
            ->whereColumn('subscribed_at', '<=', 'unsubscribed_at')
            ->where(fn ($q) => $q->whereNotNull('first_name')->orWhereNotNull('last_name')->orWhereNotNull('ip_address'))
            ->update(['first_name' => null, 'last_name' => null, 'ip_address' => null]);
    }

    /**
     * Niente: i dati azzerati non si possono ricostruire, ed era lo scopo.
     */
    public function down(): void
    {
        //
    }
};

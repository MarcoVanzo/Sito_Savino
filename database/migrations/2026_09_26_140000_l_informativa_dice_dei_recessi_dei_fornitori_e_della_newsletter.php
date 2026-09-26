<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;

/**
 * Revisione dell'informativa del 26 settembre 2026:
 *
 * - le dichiarazioni di recesso agganciate a un ordine si tengono dieci anni
 *   (prova del recesso; il rimborso si prescrive in dieci anni, art. 2946
 *   c.c.), quelle senza ordine dodici mesi — `RichiestaDiRecesso::prunable`;
 * - i destinatari distinguono i responsabili (art. 28) dai titolari autonomi
 *   (PayPal, Stripe, corriere; Meta contitolare della raccolta del pixel), e i
 *   trasferimenti dicono la base per i fornitori statunitensi (Data Privacy
 *   Framework e/o clausole contrattuali standard, artt. 45 e 46);
 * - la newsletter dichiara pixel e link tracciati, e la revoca del solo
 *   tracciamento (linee guida del Garante del 17/04/2026 sui pixel nelle
 *   email, provv. 284, adeguamento entro il 29/10/2026).
 *
 * A guardia come le altre revisioni: si riscrive solo dove è rimasta una
 * versione precedente riconosciuta dalle `firme`. Non alza
 * `ConsensoCookie::VERSIONE`: la Cookie Policy non cambia, e il pixel della
 * newsletter non è un cookie del sito.
 */
return new class extends Migration
{
    public function up(): void
    {
        TestiDelleInformative::riscriviDoveNonToccata();
    }

    /**
     * Non si annulla: il testo di prima teneva i recessi dodici mesi, mentre
     * il comando ora li tiene dieci anni, e taceva i pixel della newsletter.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

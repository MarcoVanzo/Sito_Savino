<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;

/**
 * L'informativa nomina le dichiarazioni di recesso inviate da `/recesso` e
 * dice per quanto si tengono: dodici mesi dall'invio, applicati da
 * `model:prune` (RichiestaDiRecesso::prunable).
 *
 * A guardia come le altre revisioni: si riscrive solo dove è rimasta una
 * versione precedente riconosciuta dalle `firme`. Non alza
 * `ConsensoCookie::VERSIONE`: non riguarda i cookie.
 */
return new class extends Migration
{
    public function up(): void
    {
        TestiDelleInformative::riscriviDoveNonToccata();
    }

    /**
     * Non si annulla: il testo di prima non diceva niente dei recessi.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

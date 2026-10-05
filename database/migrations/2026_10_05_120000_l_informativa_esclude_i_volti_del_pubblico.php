<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;

/**
 * Le foto della gallery sono di gioco, col pubblico sullo sfondo sfocato. Dal
 * 5 ottobre 2026 i volti di sfondo si coprono prima del riconoscimento
 * (`App\Support\VoltiDiSfondo`) e non si confrontano con nessuno: la Privacy
 * Policy lo dice, a guardia (solo dove resta la versione del 2 ottobre,
 * riconosciuta dalle `firme`).
 */
return new class extends Migration
{
    public function up(): void
    {
        TestiDelleInformative::riscriviDoveNonToccata();
    }

    /**
     * Non si annulla: il testo di prima non descrive più quello che il sito fa.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

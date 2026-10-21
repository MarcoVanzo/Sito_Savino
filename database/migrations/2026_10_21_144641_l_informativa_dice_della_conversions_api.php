<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;

/**
 * Privacy e Cookie Policy dicono della Conversions API di Meta (l'acquisto
 * comunicato anche dal server, solo con il consenso di marketing) e dei
 * segnali di Google Consent Mode. A guardia: solo dove restano le versioni del
 * 5 e del 2 ottobre 2026, riconosciute dalle `firme`.
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

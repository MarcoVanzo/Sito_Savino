<?php

use App\Http\Middleware\CachePublicResponse;
use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;

/**
 * Privacy Policy del 20 ottobre 2026: dove Meta è contitolare per il pixel
 * (art. 26 GDPR), il link al Controller Addendum di Meta, che rende
 * disponibile il contenuto essenziale dell'accordo (art. 26 §2). A guardia
 * come le altre revisioni: solo dove è rimasta la versione del 5 ottobre,
 * riconosciuta dalle `firme`.
 *
 * La Cookie Policy non cambia, quindi il banner dei cookie non torna per
 * questo. Chi si registra allo shop da oggi porta la versione 2026-10-20
 * (`users.informativa_privacy_versione`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (TestiDelleInformative::riscriviDoveNonToccata() !== []) {
            CachePublicResponse::flush();
        }
    }

    /**
     * Non si annulla: è una revisione del testo.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

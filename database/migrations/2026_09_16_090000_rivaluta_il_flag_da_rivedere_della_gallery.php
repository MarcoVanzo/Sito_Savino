<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il flag "da rivedere" della gallery cambia significato: non più "c'è un
 * volto senza tag" (acceso su 6.004 foto su 6.005 il 16/09/2026, cioè su
 * tutto) ma "c'è un volto grande che somiglia a una persona in anagrafica
 * senza raggiungere la soglia del tag". Sulle foto già analizzate il vecchio
 * flag non dice niente e si spegne; la regola nuova vale dalle prossime
 * analisi (scelta del 16/09/2026: non ripassare le 6.000 foto già fatte).
 * I tag esistenti non si toccano.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('gallery_images')
            ->whereNotNull('ai_analyzed_at')
            ->where('needs_review', true)
            ->update(['needs_review' => false]);
    }

    /**
     * Non reversibile: il valore precedente del flag non si può ricostruire,
     * e l'analisi si può sempre rilanciare.
     */
    public function down(): void {}
};

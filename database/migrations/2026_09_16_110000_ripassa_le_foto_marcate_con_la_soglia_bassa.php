<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La prima versione del flag "volto quasi riconosciuto" usava una somiglianza
 * minima di 0.90 e in due ore ha marcato 541 foto su 1.150: due volti
 * qualunque si somigliano spesso al 91-96%. La soglia sale a 0.97; le foto
 * marcate con quella bassa (le sole con il flag acceso dopo l'analisi) si
 * spengono e tornano in coda al giro orario, così vengono rivalutate. I tag
 * non si toccano.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('gallery_images')
            ->whereNotNull('ai_analyzed_at')
            ->where('needs_review', true)
            ->update(['needs_review' => false, 'ai_analyzed_at' => null]);
    }

    /**
     * Non reversibile: il flag precedente non si ricostruisce e l'analisi si
     * può sempre rilanciare.
     */
    public function down(): void {}
};

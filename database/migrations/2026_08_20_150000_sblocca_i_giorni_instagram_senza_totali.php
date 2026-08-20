<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * In produzione quindici giorni su trentuno hanno reach e follower ma zero
 * visualizzazioni, interazioni e account raggiunti, e sono marcati definitivi:
 * sono le righe nate dalla serie storica, che copre tutto il periodo, mentre i
 * totali di quei giorni non erano ancora stati scaricati. Marcati definitivi non
 * verrebbero mai più richiesti, e metà del grafico resterebbe piatta per sempre.
 *
 * Il difetto è chiuso in `InstagramDailySync` — `is_final` lo scrive solo chi
 * scarica i totali — ma le righe già marcate vanno sbloccate a mano, altrimenti
 * il job notturno continua a saltarle.
 *
 * Si sbloccano solo le righe che la serie storica ha creato: un giorno davvero
 * a zero su tutto avrebbe anche reach e follower a zero e resta com'è.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('social_insights_daily')
            ->where('is_final', true)
            ->where('views', 0)
            ->where('total_interactions', 0)
            ->where('accounts_engaged', 0)
            ->where(fn ($query) => $query->where('reach', '>', 0)->orWhere('follower_count', '>', 0))
            ->update(['is_final' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // no-op: rimarcarli definitivi vorrebbe dire richiudere il buco nei dati.
    }
};

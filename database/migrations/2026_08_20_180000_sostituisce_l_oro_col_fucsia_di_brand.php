<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'oro non è mai stato un colore del brand: la Brand & Digital Style Guide
 * 2026-2027 elenca fucsia (#F8269C), blu e bianco, e nient'altro. Il token
 * `savino-gold` è quindi diventato `savino-fucsia`.
 *
 * Il nome del token finisce anche nei contenuti: le squadre del vivaio e i
 * progetti sociali salvano in `content_data` quale colore usare, e quel valore
 * viene confrontato con il nome della classe nel componente. Senza questa
 * conversione le righe salvate resterebbero su `savino-gold`, non
 * corrisponderebbero più a nessun ramo e le schede perderebbero il colore.
 */
return new class extends Migration
{
    private const FROM = 'savino-gold';

    private const TO = 'savino-fucsia';

    public function up(): void
    {
        $this->sostituisci(self::FROM, self::TO);
    }

    public function down(): void
    {
        $this->sostituisci(self::TO, self::FROM);
    }

    private function sostituisci(string $da, string $a): void
    {
        DB::table('pages')
            ->where('content_data', 'like', '%'.$da.'%')
            ->orderBy('id')
            ->each(function (object $page) use ($da, $a) {
                DB::table('pages')
                    ->where('id', $page->id)
                    ->update(['content_data' => str_replace($da, $a, (string) $page->content_data)]);
            });
    }
};

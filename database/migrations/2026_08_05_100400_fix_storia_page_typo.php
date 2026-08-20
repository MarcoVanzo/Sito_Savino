<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corregge un refuso nel testo italiano della pagina "Storia del Club".
 *
 * Il contenuto pubblicato dice "una visiose proiettata verso il futuro": è un
 * errore di battitura, non una scelta redazionale. Viene alla luce ora perché
 * traducendo la pagina in inglese si è dovuto leggere l'italiano parola per
 * parola.
 *
 * La sostituzione è mirata sulla singola parola e si applica solo se il refuso
 * è ancora lì: se la redazione lo ha già corretto (o ha riscritto la frase),
 * la migrazione non tocca nulla.
 */
return new class extends Migration
{
    private const TYPO = 'una visiose proiettata';

    private const FIX = 'una visione proiettata';

    public function up(): void
    {
        $page = DB::table('pages')->where('slug', 'storia')->first();

        if (! $page) {
            return;
        }

        $content = json_decode((string) ($page->content ?? ''), true);

        if (! is_array($content) || ! isset($content['it']) || ! str_contains($content['it'], self::TYPO)) {
            return;
        }

        $content['it'] = str_replace(self::TYPO, self::FIX, $content['it']);

        DB::table('pages')
            ->where('id', $page->id)
            ->update(['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Non reversibile di proposito: reintrodurre un refuso non è un rollback
     * utile a nessuno.
     */
    public function down(): void {}
};

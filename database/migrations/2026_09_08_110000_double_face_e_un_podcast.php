<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Double Face e' il podcast della societa', non un magazine.
 *
 * La redazione aveva gia' corretto titolo, testo e descrizione SEO della
 * pagina, ma la frase "Il magazine ufficiale" restava nella tendina del menu:
 * e' la descrizione della voce (`menu_items.description`), non un campo della
 * pagina, e nel pannello sta sotto Menu. Qui si allineano la voce e la copia
 * inglese della pagina, che diceva ancora "magazine".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')
            ->where('url', 'like', '%/double-face%')
            ->update([
                'description' => json_encode(['it' => 'Il podcast ufficiale', 'en' => 'The official podcast'], JSON_UNESCAPED_UNICODE),
            ]);

        $pagina = DB::table('pages')->where('slug', 'double-face')->first();

        if (! $pagina) {
            return;
        }

        $modifiche = [];

        foreach (['title', 'content', 'excerpt', 'meta_description'] as $colonna) {
            $valore = $pagina->{$colonna};
            $decodificato = is_string($valore) ? json_decode($valore, true) : null;

            if (! is_array($decodificato) || ! is_string($decodificato['en'] ?? null)) {
                continue;
            }

            $inglese = str_ireplace(['The Magazine', 'official magazine'], ['The Podcast', 'official podcast'], $decodificato['en']);

            if ($inglese !== $decodificato['en']) {
                $decodificato['en'] = $inglese;
                $modifiche[$colonna] = json_encode($decodificato, JSON_UNESCAPED_UNICODE);
            }
        }

        if ($modifiche !== []) {
            DB::table('pages')->where('id', $pagina->id)->update($modifiche);
        }
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->where('url', 'like', '%/double-face%')
            ->update([
                'description' => json_encode(['it' => 'Il magazine ufficiale', 'en' => 'The official magazine'], JSON_UNESCAPED_UNICODE),
            ]);
    }
};

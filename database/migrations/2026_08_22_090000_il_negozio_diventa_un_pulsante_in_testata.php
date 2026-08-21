<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * In mezzo alle sezioni editoriali la voce "Shop Ufficiale" si perdeva: era una
 * parola come le altre in una barra di nove. Il flag `is_highlight` ora significa
 * "fuori dalla barra, dentro il pulsante pieno della testata" — cambia il modo di
 * mostrarla, non il contenuto, che resta governato dal menu del pannello.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')
            ->where('location', 'main')
            ->whereNull('parent_id')
            ->where('url', 'like', '/shop%')
            ->update([
                'is_highlight' => true,
                // "Shop Ufficiale" era il nome di una voce fra nove; dentro un
                // pulsante la seconda parola non aggiunge nulla e costa un'ottantina
                // di pixel di barra, cioe' un corpo intero per le voci che restano.
                'label' => json_encode(['it' => 'Shop', 'en' => 'Shop'], JSON_UNESCAPED_UNICODE),
            ]);
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->where('location', 'main')
            ->whereNull('parent_id')
            ->where('url', 'like', '/shop%')
            ->update([
                'is_highlight' => false,
                'label' => json_encode(['it' => 'Shop Ufficiale', 'en' => 'Official Shop'], JSON_UNESCAPED_UNICODE),
            ]);
    }
};

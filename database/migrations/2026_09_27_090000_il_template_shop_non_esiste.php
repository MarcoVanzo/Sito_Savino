<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Il pannello offriva il template "Shop" (`Public/Shop`), che non esiste:
 * non c'e' `Pages/Public/Shop.vue` e `PageController::ALLOWED_TEMPLATES` non
 * lo elenca, quindi una pagina che lo sceglieva veniva mostrata con
 * `Public/ContentPage` senza che la redazione lo sapesse. Lo shop ha rotte e
 * pagine proprie (`Public/Shop/*`); la pagina CMS `shop` e' solo un
 * contenitore di sezione.
 *
 * L'opzione esce dal pannello e le pagine che la usavano passano a
 * `Public/ContentPage`, che e' cio' che il sito mostrava gia': a schermo non
 * cambia niente. In produzione e' la sola pagina `shop` (id 9, content_data
 * vuoto). A guardia: tocca solo le righe ancora su `Public/Shop`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pages')
            ->where('template', 'Public/Shop')
            ->update(['template' => 'Public/ContentPage']);
    }

    /**
     * No-op: il valore di prima non era un template valido.
     */
    public function down(): void {}
};

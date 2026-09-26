<?php

use App\Support\TestiDelleInformative;
use Illuminate\Database\Migrations\Migration;

/**
 * Mappa e video incorporati non partono più con la pagina: aspettano il
 * consenso di marketing o un clic sul segnaposto (`ContenutoIncorporato.vue`),
 * e YouTube si incorpora da `youtube-nocookie.com`. Privacy Policy e Cookie
 * Policy dicevano che si caricavano insieme alla pagina: era vero, ora no.
 *
 * A guardia come le altre revisioni: si riscrive solo dove è rimasta una
 * versione precedente riconosciuta dalle `firme`. Alza
 * `ConsensoCookie::VERSIONE` (2026-09-26), perché il consenso di marketing
 * adesso copre anche il caricamento di mappa e video.
 */
return new class extends Migration
{
    public function up(): void
    {
        TestiDelleInformative::riscriviDoveNonToccata();
    }

    /**
     * Non si annulla: il testo di prima descriveva un comportamento che il
     * sito non ha più.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

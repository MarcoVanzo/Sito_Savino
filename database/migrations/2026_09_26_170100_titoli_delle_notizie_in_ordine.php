<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Riallinea i titoli interni delle notizie importate e toglie gli
 * `aria-level` sui paragrafi, con le regole di `news:correggi-accessibilita`
 * (App\Support\Accessibilita\TitoliDelleNotizie). Il comando resta per i
 * comunicati futuri; qui gira una volta al deploy, perche' la dichiarazione
 * di accessibilita' pubblicata insieme dice che l'archivio e' in ordine, e
 * deve esserlo da quel momento, non da quando qualcuno apre la console.
 *
 * Provato a secco sulla produzione (sola lettura, `--prova`, 26/09/2026):
 * 320 notizie. E' idempotente e non tocca nient'altro che livelli dei titoli
 * e `aria-level`; il formato di ogni riga resta quello che era.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('news:correggi-accessibilita');
    }

    /**
     * Nessun ritorno: rimettere i titoli fuori ordine significherebbe
     * ripubblicare il difetto, e i livelli originali non sono conservati.
     */
    public function down(): void
    {
        //
    }
};

<?php

use App\Jobs\ImportaLaGalleryStorica;
use App\Jobs\RigeneraLeAnteprimeMancanti;
use Illuminate\Database\Migrations\Migration;

/**
 * Rimette in coda i due lavori caduti durante il deploy precedente.
 *
 * `RigeneraLeAnteprimeMancanti` è morto con "The script tried to access a
 * property on an incomplete object": la migrazione che lo mette in coda gira
 * all'avvio del container web, e in quel momento il worker stava ancora
 * eseguendo il rilascio precedente, dove quella classe non esisteva. Adesso
 * c'è in entrambi, quindi il rilancio regge.
 *
 * È un rischio di ogni job messo in coda da una migrazione al primo rilascio
 * che lo introduce: la coda è condivisa fra due container che non si riavviano
 * insieme. Rilanciarlo al deploy successivo è la via più semplice — il lavoro
 * è idempotente e riparte da dove si era fermato.
 *
 * `ImportaLaGalleryStorica` è caduto per un timeout di dieci secondi verso il
 * sito precedente mentre chiedeva l'elenco degli album, ed essendo l'ultimo
 * anello della catena che si rimette in coda da sola l'import si è fermato a
 * 319 album su circa 335. Il sito risponde di nuovo: riparte da lì.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mai in linea: con la coda `sync` — è così nei test — il dispatch
        // eseguirebbe tutto il lavoro dentro la migrazione.
        $coda = config('queue.default') === 'sync' ? 'database' : config('queue.default');

        RigeneraLeAnteprimeMancanti::dispatch()->onConnection($coda);
        ImportaLaGalleryStorica::dispatch()->onConnection($coda);
    }

    public function down(): void {}
};

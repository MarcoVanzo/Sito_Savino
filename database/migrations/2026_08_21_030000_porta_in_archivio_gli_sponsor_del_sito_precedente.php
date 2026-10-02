<?php

use Illuminate\Database\Migrations\Migration;

/**
 * La pagina Sponsor era vuota: in archivio non c'era un solo sponsor, e online
 * si vedeva una pagina senza loghi. La redazione ha chiesto di riportare quelli
 * del sito precedente, con i rispettivi collegamenti.
 *
 * Il comando `sponsors:import-legacy` fa esattamente questo ed è idempotente
 * (la chiave è il nome). Qui viene lanciato una volta sola, al primo deploy che
 * incontra questa migrazione: sono 76 sponsor con livello, link e logo.
 *
 * Tre cautele, perché le migrazioni girano a ogni avvio del container:
 *
 * - Se in archivio c'è già qualcosa non si tocca niente. Un import che passa
 *   sopra al lavoro fatto in redazione sarebbe peggio della pagina vuota.
 * - L'import legge un sito esterno: se quello non risponde, la migrazione lo
 *   annota e prosegue. Un deploy non deve fallire perché un sito altrui è giù.
 * - Sotto i test non parte affatto: lì il database si ricrea a ogni prova e
 *   la tabella è sempre vuota, quindi si finirebbe a interrogare un sito
 *   altrui centinaia di volte per nulla.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op dal 01/10/2026: il lavoro che metteva in coda leggeva il vecchio
        // sito WordPress, che dal passaggio del dominio non esiste piu', e il
        // codice e' stato tolto. In produzione la migrazione e' gia' applicata;
        // su un database nuovo non c'e' niente da importare.
    }

    /**
     * Non reversibile: cancellare gli sponsor toglierebbe anche quelli
     * eventualmente aggiunti o corretti in redazione dopo l'import.
     */
    public function down(): void
    {
        // no-op documentato
    }
};

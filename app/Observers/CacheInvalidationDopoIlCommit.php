<?php

namespace App\Observers;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * La stessa invalidazione di CacheInvalidationObserver, ma a transazione
 * chiusa. Serve ai modelli che cambiano dentro il checkout (movimenti di
 * magazzino, giacenze delle taglie): buttata la vetrina prima del commit, una
 * richiesta concorrente la ricostruiva leggendo ancora la giacenza vecchia e
 * la rimetteva in cache per dieci minuti. Fuori da una transazione parte
 * subito, come l'observer di base.
 */
class CacheInvalidationDopoIlCommit extends CacheInvalidationObserver implements ShouldHandleEventsAfterCommit {}

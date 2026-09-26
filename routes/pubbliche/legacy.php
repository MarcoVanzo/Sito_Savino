<?php

/**
 * Gli indirizzi del vecchio sito WordPress, registrati dentro il gruppo per
 * lingua di web.php come gli altri file di `routes/pubbliche/`.
 *
 * Il dominio `savinodelbenevolley.it` punta ancora a WordPress: al passaggio
 * tutto quello che Google ha indicizzato, che gli aggregatori hanno salvato e
 * che gira sui social arriva qui. Senza queste rotte gli indirizzi a un
 * segmento cadono sulla rotta generica delle pagine CMS (404) e quelli a due
 * segmenti non corrispondono a niente.
 *
 * Le famiglie sono quelle del sitemap di Yoast del vecchio sito, contato il
 * 22/09/2026: 2988 permalink di notizie alla radice, 2565 `/tag/`, 337
 * `/gallery/`, 72 pagine, 55 `/giocatrice/`, 39 `/news-c/` e gli archivi
 * partite. Le notizie non stanno qui — la corrispondenza è lo slug e si
 * risolve sul database, in `App\Support\PermalinkVecchioSito`.
 *
 * Il file va incluso DOPO `sito.php`, così le rotte vere vincono su quelle
 * legacy che hanno lo stesso prefisso (`/gallery/data` prima di
 * `/gallery/{any}`), e PRIMA di `shop.php`, che chiude con la rotta generica
 * `/{slug}`: dopo di quella un redirect a un segmento non verrebbe mai
 * raggiunto.
 */

use App\Models\Category;
use Illuminate\Support\Facades\Route;

return function (string $namePrefix): void {
    $vai = fn (string $rotta, array $parametri = []) => redirect()->route($namePrefix.$rotta, $parametri, 301);

    // --- Pagine con un anno nel nome ---------------------------------------
    // Su WordPress ogni stagione aveva la sua pagina: `/classifica-2023-2024/`,
    // `/playoff-scudetto-2025-2026/`. Qui la pagina è una sola e mostra la
    // stagione in corso.
    $perAnno = [
        'campionato-{year}-andata' => 'stagione.risultati',
        'campionato-{year}-ritorno' => 'stagione.risultati',
        'classifica-{year}' => 'stagione.classifica',
        'cev-champions-league-{year}' => 'stagione.cev',
        'cev-challenge-cup-{year}' => 'stagione.cev',
        'coppa-italia-a1-{year}' => 'stagione.coppa-italia',
        'playoff-scudetto-{year}' => 'stagione.playoff',
        'supercoppa-italiana-a1-{year}' => 'stagione.risultati',
    ];

    foreach ($perAnno as $percorso => $destinazione) {
        Route::get('/'.$percorso, fn () => $vai($destinazione))->where('year', '.*');
    }

    // --- Pagine con un indirizzo diverso da prima --------------------------
    // Solo quelle con una destinazione che non lascia dubbi. Le pagine il cui
    // slug è rimasto lo stesso (`organigramma`, `biglietteria`, `affiliazioni`)
    // non stanno qui: la rotta generica le trova nel CMS e le porta da sé
    // all'indirizzo di sezione. Gli eventi una tantum del vecchio sito non
    // hanno un erede e restano 404.
    $pagine = [
        'video-gallery' => ['gallery', []],
        'rassegna-stampa' => ['comunicazione.page', ['slug' => 'magazine']],
        'collaboratori' => ['societa.page', ['slug' => 'organigramma']],
        'squadra' => ['stagione', []],
        'squadra-volley-femminile' => ['stagione', []],
        'atlete-b1' => ['stagione.b1', []],
        'staff-serie-b2' => ['staff', []],
        'giovanile' => ['youth.page', ['slug' => 'settore-giovanile']],
        // "Vuoi diventare una di noi?": era la pagina della Savino Del Bene
        // Volley Academy, cioe' del vivaio.
        'recruiting' => ['youth.page', ['slug' => 'settore-giovanile']],
        // Il contenitore degli archivi, senza il segmento che lo segue.
        'archivi-partite' => ['stagione.risultati', []],
        'talent-day-2026' => ['youth.page', ['slug' => 'talent-day']],
        'informativa-privacy' => ['pages.show', ['slug' => 'privacy-policy']],
        'informativa-cookie' => ['pages.show', ['slug' => 'cookie-policy']],
        'contatti_new' => ['contatti', []],
        'diventa-sponsor-3' => ['sponsor.page', ['slug' => 'diventa-sponsor']],
        'sponsor-2' => ['sponsor.page', ['slug' => 'diventa-sponsor']],
        'jam-camp' => ['summer-camp', []],
        'summer-camp-old' => ['summer-camp', []],
        'summer-camp-old2' => ['summer-camp', []],
        'mondiale-club-fivb' => ['stagione.risultati', []],
        'cev-challenge-league' => ['stagione.cev', []],
        // Le pagine legali del vecchio negozio WooCommerce
        // (shop.savinodelbenevolley.it), che ne aveva due versioni delle
        // condizioni: qui ce n'e' una sola.
        'condizioni-generali-di-vendita' => ['pages.show', ['slug' => 'condizioni-di-vendita']],
        'condizioni-di-vendita-del-negozio-online' => ['pages.show', ['slug' => 'condizioni-di-vendita']],
        'condizioni-di-spedizione' => ['pages.show', ['slug' => 'spedizioni']],
        'informativa-sui-rimborsi' => ['pages.show', ['slug' => 'resi-e-rimborsi']],
        'regolamento-e-privacy-policy-aste' => ['pages.show', ['slug' => 'regolamento-aste']],
    ];

    foreach ($pagine as $vecchio => [$rotta, $parametri]) {
        Route::get('/'.$vecchio, fn () => $vai($rotta, $parametri));
    }

    // --- PDF delle informative ----------------------------------------------
    // Dal 26/09/2026 l'informativa fornitori e' una pagina: il PDF che il
    // vecchio sito pubblicava nella libreria media porta li'. Quella
    // promozionale no: non e' mai stata nella libreria media di WordPress
    // (verificato su `wp-json/wp/v2/media` il 26/09/2026), quindi non ha un
    // vecchio indirizzo da raccogliere.
    $pdf = [
        'wp-content/uploads/2021/06/Informativa-Fornitori.pdf' => 'informativa-fornitori',
    ];

    foreach ($pdf as $vecchio => $slug) {
        Route::get('/'.$vecchio, fn () => $vai('pages.show', ['slug' => $slug]));
    }

    // --- Feed ---------------------------------------------------------------
    // Devono stare prima delle rotte di tag e categoria, che altrimenti se li
    // prendono e li mandano su una pagina HTML: chi legge un feed non saprebbe
    // che farsene. `/feed/atom/` e `/feed/rss2/` sono le altre forme che
    // WordPress serve sullo stesso contenuto.
    Route::get('/feed/{any}', fn () => $vai('news.feed'))->where('any', '.*');
    Route::get('/comments/feed', fn () => $vai('news.feed'));
    Route::get('/news-c/{any}/feed', fn () => $vai('news.feed'))->where('any', '.*');
    Route::get('/tag/{any}/feed', fn () => $vai('news.feed'))->where('any', '.*');

    // --- Categorie ----------------------------------------------------------
    // Le categorie del vecchio sito stavano su `/news-c/`, a volte annidate
    // (`/news-c/archivi-notizie/serie-a1-2016-2017/`). Quando lo slug finale
    // esiste ancora si arriva all'archivio già filtrato: prima finivano tutte
    // sull'elenco generale, e chi cercava una stagione se la doveva ritrovare
    // a mano.
    Route::get('/news-c/{any}', function (string $any) use ($namePrefix, $vai) {
        $slug = (string) collect(explode('/', $any))->filter()->last();

        return Category::where('slug', $slug)->exists()
            ? redirect()->route($namePrefix.'news.index', ['categoria' => $slug], 301)
            : $vai('news.index');
    })->where('any', '.*');

    // --- Tag ----------------------------------------------------------------
    // Erano 2565, una per ogni parola che l'ufficio stampa ha usato in dieci
    // anni. Qui non esiste una pagina per tag e non ha senso crearne una:
    // portano all'archivio delle notizie.
    Route::get('/tag/{any}', fn () => $vai('news.index'))->where('any', '.*');

    // --- Gallery e schede atleta -------------------------------------------
    // Gli album per giornata (`/gallery/giornata-1-andata-…/`) qui non
    // esistono: c'è un archivio solo, sfogliabile. Le schede `/giocatrice/`
    // avevano per slug il cognome — a volte di due persone insieme
    // (`daniele-fallani-stefano-campani`) — e non si riconducono all'atleta in
    // modo affidabile: si arriva alla rosa.
    Route::get('/gallery/{any}', fn () => $vai('gallery'))->where('any', '.*');
    Route::get('/giocatrice/{any}', fn () => $vai('stagione'))->where('any', '.*');
    Route::get('/archivi-partite/{any}', fn () => $vai('stagione.risultati'))->where('any', '.*');
};

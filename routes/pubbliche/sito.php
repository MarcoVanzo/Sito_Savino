<?php

/**
 * Rotte pubbliche del sito, registrate dentro il gruppo per lingua di web.php.
 *
 * Il file restituisce una funzione invece di appoggiarsi allo scope di chi lo
 * include: il prefisso dei nomi e' un parametro, e si vede da dove arriva. La
 * lingua non serve qui — gli indirizzi del sito sono gli stessi in entrambe, e
 * il prefisso lo mette gia' il gruppo di web.php. La usa solo lo shop, che ha
 * gli slug tradotti.
 * Sta a parte perche' il gruppo era di duecentocinquanta righe e ci si perdeva
 * fra le sezioni; qui ci sono le rotte del sito, lo shop e' nel file accanto.
 */

use App\Http\Controllers\GalleryController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PressAccreditationController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RisultatiController;
use Illuminate\Support\Facades\Route;

return function (string $namePrefix): void {
    Route::get('/', [PublicController::class, 'home'])->name('home');
    Route::get('/stagione', [PublicController::class, 'stagione'])->name('stagione');
    Route::get('/stagione/b1', [PublicController::class, 'stagioneB1'])->name('stagione.b1');
    // Le altre due squadre del vivaio hanno una pagina ciascuna, come la B1:
    // erano un'unica voce "Serie U17 & U15" che finiva in costruzione.
    Route::get('/stagione/u17', [PublicController::class, 'stagioneU17'])->name('stagione.u17');
    Route::get('/stagione/u15', [PublicController::class, 'stagioneU15'])->name('stagione.u15');
    // Indirizzo proprio del banner palmarès: apre /stagione con la
    // finestra di un'atleta già aperta, così il link è condivisibile.
    Route::get('/stagione/atleta/{slug}', [PublicController::class, 'stagioneAtleta'])
        ->name('stagione.atleta');

    // Risultati e Competizioni
    Route::get('/stagione/risultati', [RisultatiController::class, 'risultatiCampionato'])->name('stagione.risultati');
    // Deve stare prima della rotta con parametro, altrimenti "classifica"
    // verrebbe interpretato come identificativo di gara.
    Route::get('/stagione/classifica', [RisultatiController::class, 'classifica'])->name('stagione.classifica');
    Route::get('/stagione/risultati/{game}', [RisultatiController::class, 'partita'])
        ->whereNumber('game')
        ->name('stagione.partita');
    Route::get('/stagione/cev', [RisultatiController::class, 'risultatiCev'])->name('stagione.cev');
    Route::get('/stagione/coppa-italia', [RisultatiController::class, 'risultatiCoppaItalia'])->name('stagione.coppa-italia');
    // I Playoff hanno una pagina propria: prima stavano dentro Coppa Italia,
    // ma sono due competizioni con qualificazioni diverse. La pagina esiste
    // anche prima che ci sia un calendario: senza gare mostra il suo vuoto.
    Route::get('/stagione/playoff', [RisultatiController::class, 'risultatiPlayoff'])->name('stagione.playoff');

    // Foto Ufficiale e News Redirect
    Route::get('/stagione/foto-ufficiale', [PublicController::class, 'fotoUfficiale'])->name('stagione.foto-ufficiale');
    Route::get('/stagione/news', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'news.index');
    })->name('stagione.news');

    // Redirect legacy per compatibilità. La query passa: `/risultati?squadra=savino`
    // deve arrivare al calendario già filtrato.
    Route::get('/risultati', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.risultati', request()->query());
    })->name('risultati');
    Route::get('/classifica', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.classifica');
    })->name('classifica');

    Route::get('/gallery', [GalleryController::class, 'gallery'])->name('gallery');
    // Resto dell'archivio, caricato dal client dopo il primo render
    Route::get('/gallery/data', [GalleryController::class, 'galleryData'])->name('gallery.data');
    Route::get('/gallery/atleta/{slug}', [GalleryController::class, 'galleryAtleta'])->name('gallery.atleta');
    Route::get('/gallery/atleta/{slug}/data', [GalleryController::class, 'galleryData'])->name('gallery.atleta.data');
    Route::get('/staff', [PublicController::class, 'staff'])->name('staff');
    Route::get('/societa', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'societa.page', ['slug' => 'storia']);
    })->name('societa');
    Route::get('/societa/{slug}', [PageController::class, 'show'])->name('societa.page');
    Route::get('/sponsor', [PublicController::class, 'sponsor'])->name('sponsor');

    // Sponsor routes
    Route::get('/sponsor/nostri-sponsor', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'sponsor', [], 301);
    })->name('sponsor.nostri-sponsor');
    Route::get('/sponsor/{slug}', [PageController::class, 'show'])->name('sponsor.page');

    // Ticketing routes
    // La sezione porta alla biglietteria: prima `/ticketing` cadeva sulla
    // rotta generica e serviva una terza pagina con lo stesso modello, che
    // aveva ancora il listino di esempio (15/99/199 EUR). La redazione
    // modificava "Biglietteria" e online vedeva un'altra pagina.
    Route::get('/ticketing', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'ticketing.page', ['slug' => 'biglietteria'], 301);
    })->name('ticketing');
    Route::get('/ticketing/{slug}', [PageController::class, 'show'])->name('ticketing.page');

    // Youth routes
    Route::get('/youth/b1-u19', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.b1', [], 301);
    })->name('youth.b1-u19');
    Route::get('/youth/u17', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.u17', [], 301);
    })->name('youth.u17');
    Route::get('/youth/u15', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.u15', [], 301);
    })->name('youth.u15');
    // La vecchia voce unica: chi arriva da un link salvato o dai motori di
    // ricerca finisce sulla piu' grande delle due, non su una pagina "in
    // costruzione" che ormai non esiste.
    Route::get('/youth/u17-u15', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.u17', [], 301);
    })->name('youth.u17-u15');
    // Stessa cosa per il vivaio: `/youth` serviva una copia con i testi del
    // seeder ("Formare campioni dentro e fuori dal campo") mentre la
    // redazione scriveva su Settore Giovanile.
    Route::get('/youth', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'youth.page', ['slug' => 'settore-giovanile'], 301);
    })->name('youth');
    Route::get('/youth/{slug}', [PageController::class, 'show'])->name('youth.page');

    // Summer Camp routes
    Route::get('/summer-camp/info', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'pages.show', ['slug' => 'summer-camp'], 301);
    })->name('summer-camp.info');
    // Rimandava a una pagina che in produzione non esiste, quindi il
    // vecchio indirizzo rispondeva 404. Una redirezione deve portare a
    // qualcosa che c'e' sempre: la sezione.
    Route::get('/summer-camp/iscrizione', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'summer-camp', [], 301);
    })->name('summer-camp.iscrizione');
    // La pagina della sezione vive sul suo stesso indirizzo: prima
    // `/summer-camp` rimbalzava su `/summer-camp/summer-camp`.
    Route::get('/summer-camp', [PageController::class, 'show'])
        ->defaults('slug', 'summer-camp')
        ->name('summer-camp');
    Route::get('/summer-camp/{slug}', [PageController::class, 'show'])->name('summer-camp.page');

    // Sociale routes
    Route::get('/sociale/progetti', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'sociale.page', ['slug' => 'progetti-sociali'], 301);
    })->name('sociale.progetti');
    Route::get('/sociale/aste', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'shop.auctions.index', [], 301);
    })->name('sociale.aste');
    Route::get('/sociale', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'sociale.page', ['slug' => 'volley-4-all'], 301);
    })->name('sociale');
    Route::get('/sociale/{slug}', [PageController::class, 'show'])->name('sociale.page');

    // Comunicazione routes
    Route::get('/comunicazione/accrediti', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'comunicazione.page', ['slug' => 'accrediti-stampa'], 301);
    })->name('comunicazione.accrediti');
    Route::get('/comunicazione/cartelle', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'comunicazione.page', ['slug' => 'cartelle-stampa'], 301);
    })->name('comunicazione.cartelle');
    Route::get('/comunicazione', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'comunicazione.page', ['slug' => 'accrediti-stampa'], 301);
    })->name('comunicazione');
    // Le richieste di accredito arrivano dal modulo nella pagina Comunicazione
    // e finiscono in "Richieste Accrediti" nel pannello, oltre che a press@.
    Route::post('/comunicazione/accrediti', [PressAccreditationController::class, 'submit'])
        ->middleware('throttle:5,1,comunicazione.accrediti.submit')
        ->name('comunicazione.accrediti.submit');
    Route::get('/comunicazione/{slug}', [PageController::class, 'show'])->name('comunicazione.page');
    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    // Il feed RSS delle notizie, che la Lega Pallavolo Serie A Femminile
    // riprende per la rassegna delle società. L'indirizzo canonico è `/feed`,
    // lo stesso che serviva il vecchio sito WordPress: chi lo aveva già
    // registrato non deve rifarlo. Deve stare prima di `/news/{slug}`,
    // altrimenti "feed" verrebbe letto come lo slug di una notizia.
    Route::get('/news/feed', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'news.feed', [], 301);
    })->name('news.feed.alias');
    Route::get('/rss', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'news.feed', [], 301);
    })->name('news.feed.rss');
    Route::get('/feed', [NewsController::class, 'feed'])->name('news.feed');
    Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');
};

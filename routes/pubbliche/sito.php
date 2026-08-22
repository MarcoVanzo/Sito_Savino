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
    // Indirizzo proprio del banner palmarès: apre /stagione con la
    // finestra di un'atleta già aperta, così il link è condivisibile.
    Route::get('/stagione/atleta/{slug}', [PublicController::class, 'stagioneAtleta'])
        ->name('stagione.atleta');
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

    // Redirect legacy per compatibilità
    Route::get('/risultati', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.risultati');
    })->name('risultati');
    Route::get('/classifica', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.classifica');
    })->name('classifica');

    // --- Redirect SEO Legacy (Dal Vecchio Sito) ---
    Route::get('/campionato-{year}-andata', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.risultati', [], 301);
    })->where('year', '.*');
    Route::get('/campionato-{year}-ritorno', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.risultati', [], 301);
    })->where('year', '.*');
    Route::get('/classifica-{year}', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.classifica', [], 301);
    })->where('year', '.*');
    Route::get('/cev-champions-league-{year}', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.cev', [], 301);
    })->where('year', '.*');
    Route::get('/news-c/{any}', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'news.index', [], 301);
    })->where('any', '.*');

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
    Route::get('/ticketing/{slug}', [PageController::class, 'show'])->name('ticketing.page');

    // Youth routes
    Route::get('/youth/b1-u19', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'stagione.b1', [], 301);
    })->name('youth.b1-u19');
    Route::get('/youth/u17-u15', function () use ($namePrefix) {
        return redirect()->route($namePrefix.'in-costruzione', [], 301);
    })->name('youth.u17-u15');
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
        ->middleware('throttle:5,1')
        ->name('comunicazione.accrediti.submit');
    Route::get('/comunicazione/{slug}', [PageController::class, 'show'])->name('comunicazione.page');
    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');
};

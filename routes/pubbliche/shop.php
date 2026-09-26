<?php

/**
 * Rotte dello shop e delle aste, registrate dentro il gruppo per lingua di
 * web.php. Gli slug sono localizzati: /shop/prodotto in italiano,
 * /shop/product in inglese.
 *
 * Come per il file accanto, `$loc` e `$namePrefix` sono parametri della
 * funzione restituita, non variabili ereditate da chi include.
 */

use App\Http\Controllers\ConsensoCookieController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\AuctionCheckoutController;
use App\Http\Controllers\Shop\AuctionController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\RecessoController;
use App\Http\Controllers\Shop\ShopAuthController;
use App\Http\Controllers\Shop\ShopController;
use App\Http\Controllers\Shop\ValidateCouponController;
use App\Http\Middleware\TrackShopPageView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return function (string $loc, string $namePrefix): void {
    // =============================================
    // Shop Routes — slugs localizzati
    // =============================================
    $shopSlugs = [
        'cerca' => $loc === 'en' ? 'search' : 'cerca',
        'categoria' => $loc === 'en' ? 'category' : 'categoria',
        'prodotto' => $loc === 'en' ? 'product' : 'prodotto',
        'carrello' => $loc === 'en' ? 'cart' : 'carrello',
        'conferma' => $loc === 'en' ? 'confirmed' : 'conferma',
        'annullato' => $loc === 'en' ? 'cancelled' : 'annullato',
        'ordine' => $loc === 'en' ? 'order' : 'ordine',
        'ricevuta' => $loc === 'en' ? 'receipt' : 'ricevuta',
        'i-miei-dati' => $loc === 'en' ? 'my-data' : 'i-miei-dati',
        'ordini' => $loc === 'en' ? 'orders' : 'ordini',
        'registrati' => $loc === 'en' ? 'register' : 'registrati',
        'contatti' => $loc === 'en' ? 'contacts' : 'contatti',
        'guida-taglie' => $loc === 'en' ? 'size-guide' : 'guida-taglie',
        'aste' => $loc === 'en' ? 'auctions' : 'aste',
    ];

    Route::prefix('shop')->middleware([TrackShopPageView::class])->group(function () use ($shopSlugs, $namePrefix) {
        // Redirect legacy per le sotto-voci dello shop (evita 404 per vecchi menu o segnalibri)
        // Portava alla sola linea Home: "kit-gara" e' il reparto che
        // raccoglie Home, Away e Champions come linguette.
        Route::get('/kit-gara', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'shop.category', ['category' => 'kit-gara'], 301);
        });
        Route::get('/abbigliamento', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'shop.category', ['category' => 'abbigliamento'], 301);
        });
        // Portava alle aste: sono due cose diverse, l'outlet e' merce a
        // prezzo ridotto e le aste sono le aste. La redazione le vuole
        // separate anche nel menu.
        Route::get('/outlet', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'shop.category', ['category' => 'outlet'], 301);
        });

        // Public shop pages
        Route::get('/', [ShopController::class, 'index'])->name('shop');
        Route::get('/'.$shopSlugs['cerca'], [ShopController::class, 'search'])->name('shop.search');
        Route::get('/'.$shopSlugs['categoria'].'/{category:slug}', [ShopController::class, 'categoryShow'])->name('shop.category');
        Route::get('/'.$shopSlugs['prodotto'].'/{product:slug}', [ShopController::class, 'productShow'])->name('shop.product');

        // Size Guide & Shop Contacts
        Route::get('/'.$shopSlugs['guida-taglie'], [ShopController::class, 'sizeGuide'])->name('shop.size-guide');
        Route::get('/'.$shopSlugs['contatti'], [ShopController::class, 'shopContacts'])->name('shop.contacts');

        // Cart (web routes with CSRF)
        Route::get('/'.$shopSlugs['carrello'], [CartController::class, 'index'])->name('shop.cart');
        Route::post('/'.$shopSlugs['carrello'], [CartController::class, 'store'])->middleware('throttle:30,1,shop.cart.store')->name('shop.cart.store');
        Route::patch('/'.$shopSlugs['carrello'].'/{cartItem}', [CartController::class, 'update'])->middleware('throttle:30,1,shop.cart.update')->name('shop.cart.update');
        Route::delete('/'.$shopSlugs['carrello'].'/{cartItem}', [CartController::class, 'destroy'])->middleware('throttle:30,1,shop.cart.destroy')->name('shop.cart.destroy');
        Route::get('/'.$shopSlugs['carrello'].'/count', [CartController::class, 'count'])->name('shop.cart.count');
        Route::get('/'.$shopSlugs['carrello'].'/data', [CartController::class, 'data'])->name('shop.cart.data');

        // Checkout
        Route::get('/checkout', [CheckoutController::class, 'show'])->name('shop.checkout');
        Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:5,1,shop.checkout.store')->name('shop.checkout.store');
        Route::post('/checkout/validate-coupon', ValidateCouponController::class)->middleware('throttle:10,1,shop.checkout.validate-coupon')->name('shop.checkout.validate-coupon');
        Route::get('/checkout/'.$shopSlugs['conferma'].'/{orderToken}', [CheckoutController::class, 'success'])->name('shop.checkout.success');
        Route::get('/checkout/'.$shopSlugs['annullato'].'/{orderToken}', [CheckoutController::class, 'cancel'])->name('shop.checkout.cancel');
        Route::post('/checkout/retry/{orderToken}', [CheckoutController::class, 'retryPayment'])->middleware('throttle:5,1,shop.checkout.retry')->name('shop.checkout.retry');

        // Order tracking (guest via token)
        Route::get('/'.$shopSlugs['ordine'].'/{orderNumber}', [OrderController::class, 'show'])->name('shop.order.show');
        Route::get('/'.$shopSlugs['ordine'].'/{orderToken}/'.$shopSlugs['ricevuta'], [OrderController::class, 'downloadReceipt'])->name('shop.order.receipt');

        // Auth-only shop routes
        Route::middleware('auth')->group(function () use ($shopSlugs) {
            Route::get('/'.$shopSlugs['ordini'], [OrderController::class, 'index'])->name('shop.orders');

            // Il mio account: dati, esportazione (art. 20 GDPR) e
            // cancellazione (art. 17). Vedi AccountController.
            Route::get('/account', [AccountController::class, 'show'])->name('shop.account');
            Route::get('/account/'.$shopSlugs['i-miei-dati'], [AccountController::class, 'esporta'])
                ->middleware('throttle:5,1,shop.account.export')
                ->name('shop.account.export');
            Route::delete('/account', [AccountController::class, 'cancella'])
                ->middleware('throttle:5,1,shop.account.destroy')
                ->name('shop.account.destroy');
        });

        // Shop registration
        Route::middleware('guest')->group(function () use ($shopSlugs) {
            Route::get('/'.$shopSlugs['registrati'], [ShopAuthController::class, 'showRegister'])->name('shop.register');
            Route::post('/'.$shopSlugs['registrati'], [ShopAuthController::class, 'register'])->middleware('throttle:5,1,shop.register.store')->name('shop.register.store');
        });

        // Aste (public)
        Route::prefix($shopSlugs['aste'])->middleware('auctions.enabled')->group(function () {
            Route::get('/', [AuctionController::class, 'index'])->name('shop.auctions.index');
            Route::get('/{auction}', [AuctionController::class, 'show'])->name('shop.auctions.show');

            // Bidding (auth + carta verificata)
            Route::middleware(['auth', 'verified.payment'])->group(function () {
                Route::post('/{auction}/bid', [AuctionController::class, 'bid'])
                    ->middleware('throttle:12,1,shop.auctions.bid')
                    ->name('shop.auctions.bid');
            });
        });

        // Checkout asta vincitore
        Route::middleware(['auth', 'auctions.enabled'])->group(function () {
            Route::get('/checkout/asta/{token}', [AuctionCheckoutController::class, 'show'])->name('shop.auction-checkout.show');
            Route::post('/checkout/asta/{token}', [AuctionCheckoutController::class, 'store'])
                ->middleware('throttle:3,1,shop.auction-checkout.store')
                ->name('shop.auction-checkout.store');
            Route::get('/checkout/asta/{token}/conferma', [AuctionCheckoutController::class, 'success'])->name('shop.auction-checkout.success');
            Route::get('/checkout/asta/{token}/annullato', [AuctionCheckoutController::class, 'cancel'])->name('shop.auction-checkout.cancel');
        });
    });
    // Recesso online (art. 54-bis Codice del Consumo): fuori dal prefisso
    // /shop perche' il link sta nel footer di tutto il sito, e prima della
    // rotta generica delle pagine CMS che chiude questo file. Lo slug si
    // traduce come gli altri (`/en/withdrawal`): link di footer, dettaglio
    // ordine ed email passano da route(), non dall'indirizzo scritto a mano.
    $recesso = $loc === 'en' ? 'withdrawal' : 'recesso';
    Route::get('/'.$recesso, [RecessoController::class, 'show'])->name('recesso');
    // Limite stretto: la POST manda subito un'email a un indirizzo scritto
    // da chi compila, con testo suo dentro. Tre dichiarazioni ogni dieci
    // minuti bastano a chiunque receda davvero, e non fanno del modulo un
    // modo per spedire posta a nome della societa'.
    Route::post('/'.$recesso, [RecessoController::class, 'store'])->middleware('throttle:3,10,recesso.store')->name('recesso.store');
    // La ricevuta ha un indirizzo suo, firmato: ricaricando la pagina dopo
    // l'invio si rilegge la ricevuta invece di ritrovarsi il modulo vuoto (e
    // mandare una seconda dichiarazione).
    Route::get('/'.$recesso.'/'.$shopSlugs['ricevuta'].'/{richiesta}', [RecessoController::class, 'ricevuta'])
        ->middleware('signed')
        ->name('recesso.ricevuta');

    if ($recesso !== 'recesso') {
        // Fino al 26/09/2026 l'inglese stava su `/en/recesso`: il link e'
        // nelle email di conferma gia' spedite. Il 301 si porta dietro la
        // query (`?ordine=...&token=...`).
        Route::get('/recesso', fn (Request $request) => redirect()->to(
            route($namePrefix.'recesso', $request->query()), 301,
        ));
        // Le ricevute firmate gia' emesse su quell'indirizzo: la firma copre
        // il percorso, quindi un redirect la invaliderebbe. Restano leggibili
        // dove sono state firmate, per i 90 giorni della loro scadenza.
        Route::get('/recesso/ricevuta/{richiesta}', [RecessoController::class, 'ricevuta'])
            ->middleware('signed')
            ->name('recesso.ricevuta.indirizzo_precedente');
    }

    Route::get('/'.$shopSlugs['contatti'], [PublicController::class, 'contatti'])->name('contatti');
    Route::post('/'.$shopSlugs['contatti'], [ContactController::class, 'submit'])->middleware('throttle:5,1,contatti.submit')->name('contatti.submit');
    Route::post('/newsletter', [NewsletterController::class, 'subscribe'])
        ->middleware('throttle:5,1,newsletter.subscribe')
        ->name('newsletter.subscribe');

    // Il registro delle scelte fatte sul banner dei cookie. L'indirizzo non si
    // traduce: non lo digita nessuno, lo chiama il banner. Il limite è largo
    // perché una persona sola può cambiare idea più volte di seguito, e un
    // consenso rifiutato per troppe richieste sarebbe una prova persa.
    Route::post('/consenso-cookie', [ConsensoCookieController::class, 'registra'])
        ->middleware('throttle:20,1,consenso-cookie.registra')
        ->name('consenso-cookie.registra');

    // Disiscrizione: l'URL è firmato, non serve autenticazione. Il GET
    // mostra solo la conferma, la POST esegue (vedi NewsletterController).
    $disiscriviti = $loc === 'en' ? 'unsubscribe' : 'disiscriviti';
    Route::get('/newsletter/'.$disiscriviti.'/{subscriber}', [NewsletterController::class, 'showUnsubscribe'])
        ->middleware('signed')
        ->name('newsletter.unsubscribe.show');
    Route::post('/newsletter/'.$disiscriviti.'/{subscriber}', [NewsletterController::class, 'unsubscribe'])
        ->middleware(['signed', 'throttle:10,1,newsletter.unsubscribe'])
        ->name('newsletter.unsubscribe');

    // Preferenze (linee guida del Garante del 17/04/2026 sui pixel nelle
    // email): dal link in fondo a ogni newsletter si revoca il solo
    // tracciamento o tutto. Stesso schema firmato della disiscrizione.
    $preferenze = $loc === 'en' ? 'preferences' : 'preferenze';
    Route::get('/newsletter/'.$preferenze.'/{subscriber}', [NewsletterController::class, 'showPreferenze'])
        ->middleware('signed')
        ->name('newsletter.preferenze.show');
    Route::post('/newsletter/'.$preferenze.'/{subscriber}/'.($loc === 'en' ? 'no-tracking' : 'senza-tracciamento'), [NewsletterController::class, 'senzaTracciamento'])
        ->middleware(['signed', 'throttle:10,1,newsletter.senza-tracciamento'])
        ->name('newsletter.preferenze.senza-tracciamento');

    // Doppio opt-in: il link dell'email di conferma porta a una pagina con un
    // pulsante (GET), e la conferma avviene in POST — stesso schema della
    // disiscrizione, per la stessa ragione (vedi NewsletterController).
    $conferma = $loc === 'en' ? 'confirm' : 'conferma';
    Route::get('/newsletter/'.$conferma.'/{subscriber}', [NewsletterController::class, 'showConferma'])
        ->middleware('signed')
        ->name('newsletter.conferma.show');
    Route::post('/newsletter/'.$conferma.'/{subscriber}', [NewsletterController::class, 'conferma'])
        ->middleware(['signed', 'throttle:10,1,newsletter.conferma'])
        ->name('newsletter.conferma');
    Route::get('/in-costruzione', [PublicController::class, 'underConstruction'])->name('in-costruzione');

    // Rotta dinamica per le pagine del CMS (CATCH-ALL)
    Route::get('/{slug}', [PageController::class, 'show'])
        ->where('slug', '^(?!(?:admin|api|filament|livewire|storage|_debugbar|_ignition|dashboard|profile|login|register|logout|forgot-password|reset-password|verify-email|confirm-password|email|password|stagione|risultati|classifica|gallery|staff|societa|sponsor|news|shop|contatti|contacts|in-costruzione|en)$)[^/]+$')
        ->name('pages.show');
};

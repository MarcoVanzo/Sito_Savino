<?php

/**
 * Rotte dello shop e delle aste, dentro il gruppo per lingua definito in
 * web.php. Gli slug sono localizzati: /shop/prodotto in italiano,
 * /shop/product in inglese.
 *
 * @var string $loc
 * @var string $namePrefix
 */

use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Shop\AuctionCheckoutController;
use App\Http\Controllers\Shop\AuctionController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\ShopAuthController;
use App\Http\Controllers\Shop\ShopController;
use App\Http\Controllers\Shop\ValidateCouponController;
use App\Http\Middleware\TrackShopPageView;
use Illuminate\Support\Facades\Route;

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
    Route::post('/'.$shopSlugs['carrello'], [CartController::class, 'store'])->middleware('throttle:30,1')->name('shop.cart.store');
    Route::patch('/'.$shopSlugs['carrello'].'/{cartItem}', [CartController::class, 'update'])->middleware('throttle:30,1')->name('shop.cart.update');
    Route::delete('/'.$shopSlugs['carrello'].'/{cartItem}', [CartController::class, 'destroy'])->middleware('throttle:30,1')->name('shop.cart.destroy');
    Route::get('/'.$shopSlugs['carrello'].'/count', [CartController::class, 'count'])->name('shop.cart.count');
    Route::get('/'.$shopSlugs['carrello'].'/data', [CartController::class, 'data'])->name('shop.cart.data');

    // Checkout
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('shop.checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:5,1')->name('shop.checkout.store');
    Route::post('/checkout/validate-coupon', ValidateCouponController::class)->middleware('throttle:10,1')->name('shop.checkout.validate-coupon');
    Route::get('/checkout/'.$shopSlugs['conferma'].'/{orderToken}', [CheckoutController::class, 'success'])->name('shop.checkout.success');
    Route::get('/checkout/'.$shopSlugs['annullato'].'/{orderToken}', [CheckoutController::class, 'cancel'])->name('shop.checkout.cancel');
    Route::post('/checkout/retry/{orderToken}', [CheckoutController::class, 'retryPayment'])->middleware('throttle:5,1')->name('shop.checkout.retry');

    // Order tracking (guest via token)
    Route::get('/'.$shopSlugs['ordine'].'/{orderNumber}', [OrderController::class, 'show'])->name('shop.order.show');
    Route::get('/'.$shopSlugs['ordine'].'/{orderToken}/'.$shopSlugs['ricevuta'], [OrderController::class, 'downloadReceipt'])->name('shop.order.receipt');

    // Auth-only shop routes
    Route::middleware('auth')->group(function () use ($shopSlugs) {
        Route::get('/'.$shopSlugs['ordini'], [OrderController::class, 'index'])->name('shop.orders');
    });

    // Shop registration
    Route::middleware('guest')->group(function () use ($shopSlugs) {
        Route::get('/'.$shopSlugs['registrati'], [ShopAuthController::class, 'showRegister'])->name('shop.register');
        Route::post('/'.$shopSlugs['registrati'], [ShopAuthController::class, 'register'])->middleware('throttle:5,1')->name('shop.register.store');
    });

    // Aste (public)
    Route::prefix($shopSlugs['aste'])->middleware('auctions.enabled')->group(function () {
        Route::get('/', [AuctionController::class, 'index'])->name('shop.auctions.index');
        Route::get('/{auction}', [AuctionController::class, 'show'])->name('shop.auctions.show');

        // Bidding (auth + carta verificata)
        Route::middleware(['auth', 'verified.payment'])->group(function () {
            Route::post('/{auction}/bid', [AuctionController::class, 'bid'])
                ->middleware('throttle:12,1')
                ->name('shop.auctions.bid');
        });
    });

    // Checkout asta vincitore
    Route::middleware(['auth', 'auctions.enabled'])->group(function () {
        Route::get('/checkout/asta/{token}', [AuctionCheckoutController::class, 'show'])->name('shop.auction-checkout.show');
        Route::post('/checkout/asta/{token}', [AuctionCheckoutController::class, 'store'])
            ->middleware('throttle:3,1')
            ->name('shop.auction-checkout.store');
        Route::get('/checkout/asta/{token}/conferma', [AuctionCheckoutController::class, 'success'])->name('shop.auction-checkout.success');
        Route::get('/checkout/asta/{token}/annullato', [AuctionCheckoutController::class, 'cancel'])->name('shop.auction-checkout.cancel');
    });
});
Route::get('/'.$shopSlugs['contatti'], [PublicController::class, 'contatti'])->name('contatti');
Route::post('/'.$shopSlugs['contatti'], [ContactController::class, 'submit'])->middleware('throttle:5,1')->name('contatti.submit');
Route::post('/newsletter', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:5,1')
    ->name('newsletter.subscribe');

// Disiscrizione: l'URL è firmato, non serve autenticazione. Il GET
// mostra solo la conferma, la POST esegue (vedi NewsletterController).
$disiscriviti = $loc === 'en' ? 'unsubscribe' : 'disiscriviti';
Route::get('/newsletter/'.$disiscriviti.'/{subscriber}', [NewsletterController::class, 'showUnsubscribe'])
    ->middleware('signed')
    ->name('newsletter.unsubscribe.show');
Route::post('/newsletter/'.$disiscriviti.'/{subscriber}', [NewsletterController::class, 'unsubscribe'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('newsletter.unsubscribe');
Route::get('/in-costruzione', [PublicController::class, 'underConstruction'])->name('in-costruzione');

// Rotta dinamica per le pagine del CMS (CATCH-ALL)
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!(?:admin|api|filament|livewire|storage|_debugbar|_ignition|dashboard|profile|login|register|logout|forgot-password|reset-password|verify-email|confirm-password|email|password|stagione|risultati|classifica|gallery|staff|societa|sponsor|news|shop|contatti|contacts|in-costruzione|en)$)[^/]+$')
    ->name('pages.show');

<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Shop\AuctionCheckoutController;
use App\Http\Controllers\Shop\AuctionController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\PaymentVerificationController;
use App\Http\Controllers\Shop\ShopAuthController;
use App\Http\Controllers\Shop\ShopController;
use App\Http\Controllers\Shop\ValidateCouponController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ServeSocialCrawlerMeta;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackShopPageView;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/auth.php';

// Redirect 301 for the old WooCommerce subdomain
Route::domain('shop.savinodelbenevolley.it')->group(function () {
    Route::any('/{any?}', function ($any = null) {
        return redirect()->to(url('/shop/'.$any), 301);
    })->where('any', '.*');
});

$locales = ['it', 'en'];

foreach ($locales as $loc) {
    $prefix = $loc === 'it' ? '' : $loc;
    $namePrefix = $loc === 'it' ? '' : "$loc.";

    Route::middleware([
        'throttle:web',
        ServeSocialCrawlerMeta::class,
        SetLocale::class.':'.$loc,
    ])->prefix($prefix)->name($namePrefix)->group(function () use ($loc) {
        Route::get('/', [PublicController::class, 'home'])->name('home');
        Route::get('/stagione', [PublicController::class, 'stagione'])->name('stagione');
        Route::get('/stagione/b1', [PublicController::class, 'stagioneB1'])->name('stagione.b1');

        // Risultati e Competizioni
        Route::get('/stagione/risultati', [PublicController::class, 'risultatiCampionato'])->name('stagione.risultati');
        Route::get('/stagione/cev', [PublicController::class, 'risultatiCev'])->name('stagione.cev');
        Route::get('/stagione/coppa-italia', [PublicController::class, 'risultatiCoppaItalia'])->name('stagione.coppa-italia');

        // Foto Ufficiale e News Redirect
        Route::get('/stagione/foto-ufficiale', [PublicController::class, 'fotoUfficiale'])->name('stagione.foto-ufficiale');
        Route::get('/stagione/news', function () {
            return redirect()->route('news.index');
        })->name('stagione.news');

        // Redirect legacy per compatibilità
        Route::get('/risultati', function () {
            return redirect()->route('stagione.risultati');
        })->name('risultati');
        Route::get('/gallery', [PublicController::class, 'gallery'])->name('gallery');
        Route::get('/gallery/atleta/{slug}', [PublicController::class, 'galleryAtleta'])->name('gallery.atleta');
        Route::get('/staff', [PublicController::class, 'staff'])->name('staff');
        Route::get('/societa/organigramma', [PublicController::class, 'organigramma'])->name('organigramma');
        Route::get('/societa/{slug}', [PageController::class, 'show'])->name('societa.page');
        Route::get('/sponsor', [PublicController::class, 'sponsor'])->name('sponsor');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');

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

        Route::prefix('shop')->middleware([TrackShopPageView::class])->group(function () use ($shopSlugs) {
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
        Route::get('/in-costruzione', [PublicController::class, 'underConstruction'])->name('in-costruzione');

        // Rotta dinamica per le pagine del CMS (CATCH-ALL)
        Route::get('/{slug}', [PageController::class, 'show'])
            ->where('slug', '^(?!(?:admin|api|filament|livewire|storage|_debugbar|_ignition|dashboard|profile|login|register|logout|forgot-password|reset-password|verify-email|confirm-password|email|password|stagione|risultati|gallery|staff|societa|sponsor|news|shop|contatti|contacts|in-costruzione|en)$)[^/]+$')
            ->name('pages.show');
    });
}

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', EnsureUserIsActive::class])->name('dashboard');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Verifica metodo di pagamento (per aste)
    Route::get('/account/verifica-pagamento', [PaymentVerificationController::class, 'show'])->name('account.payment-verification');
    Route::post('/account/verifica-pagamento', [PaymentVerificationController::class, 'store'])->name('account.payment-verification.store');
    Route::get('/account/verifica-pagamento/completata', [PaymentVerificationController::class, 'success'])->name('account.payment-verification.success');
});

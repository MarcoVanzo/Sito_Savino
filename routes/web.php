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
    ])->prefix($prefix)->name($namePrefix)->group(function () use ($loc, $namePrefix) {
        Route::get('/', [PublicController::class, 'home'])->name('home');
        Route::get('/stagione', [PublicController::class, 'stagione'])->name('stagione');
        Route::get('/stagione/b1', [PublicController::class, 'stagioneB1'])->name('stagione.b1');

        // Risultati e Competizioni
        Route::get('/stagione/risultati', [PublicController::class, 'risultatiCampionato'])->name('stagione.risultati');
        Route::get('/stagione/cev', [PublicController::class, 'risultatiCev'])->name('stagione.cev');
        Route::get('/stagione/coppa-italia', [PublicController::class, 'risultatiCoppaItalia'])->name('stagione.coppa-italia');

        // Foto Ufficiale e News Redirect
        Route::get('/stagione/foto-ufficiale', [PublicController::class, 'fotoUfficiale'])->name('stagione.foto-ufficiale');
        Route::get('/stagione/news', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'news.index');
        })->name('stagione.news');

        // Redirect legacy per compatibilità
        Route::get('/risultati', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'stagione.risultati');
        })->name('risultati');

        // --- Redirect SEO Legacy (Dal Vecchio Sito) ---
        Route::get('/campionato-{year}-andata', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'stagione.risultati', [], 301);
        })->where('year', '.*');
        Route::get('/campionato-{year}-ritorno', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'stagione.risultati', [], 301);
        })->where('year', '.*');
        Route::get('/classifica-{year}', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'stagione.risultati', [], 301);
        })->where('year', '.*');
        Route::get('/cev-champions-league-{year}', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'stagione.cev', [], 301);
        })->where('year', '.*');
        Route::get('/news-c/{any}', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'news.index', [], 301);
        })->where('any', '.*');

        Route::get('/gallery', [PublicController::class, 'gallery'])->name('gallery');
        Route::get('/gallery/atleta/{slug}', [PublicController::class, 'galleryAtleta'])->name('gallery.atleta');
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
        Route::get('/summer-camp/iscrizione', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'in-costruzione', [], 301);
        })->name('summer-camp.iscrizione');
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
        Route::get('/comunicazione/magazine', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'comunicazione.page', ['slug' => 'double-face'], 301);
        })->name('comunicazione.magazine');
        Route::get('/comunicazione', function () use ($namePrefix) {
            return redirect()->route($namePrefix.'comunicazione.page', ['slug' => 'accrediti-stampa'], 301);
        })->name('comunicazione');
        Route::get('/comunicazione/{slug}', [PageController::class, 'show'])->name('comunicazione.page');
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

        Route::prefix('shop')->middleware([TrackShopPageView::class])->group(function () use ($shopSlugs, $namePrefix) {
            // Redirect legacy per le sotto-voci dello shop (evita 404 per vecchi menu o segnalibri)
            Route::get('/kit-gara', function () use ($namePrefix) {
                return redirect()->route($namePrefix.'shop.category', ['category' => 'kit-gara-25-26'], 301);
            });
            Route::get('/abbigliamento', function () use ($namePrefix) {
                return redirect()->route($namePrefix.'shop.category', ['category' => 'abbigliamento'], 301);
            });
            Route::get('/outlet', function () use ($namePrefix) {
                return redirect()->route($namePrefix.'shop.auctions.index', [], 301);
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

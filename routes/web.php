<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ServeSocialCrawlerMeta;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/auth.php';

// Redirect 301 for the old WooCommerce subdomain
Route::domain('shop.savinodelbenevolley.it')->group(function () {
    Route::any('/{any?}', function ($any = null) {
        return redirect()->to(url('/shop/' . $any), 301);
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
        Route::get('/risultati', [PublicController::class, 'risultati'])->name('risultati');
        Route::get('/gallery', [PublicController::class, 'gallery'])->name('gallery');
        Route::get('/gallery/atleta/{slug}', [PublicController::class, 'galleryAtleta'])->name('gallery.atleta');
        Route::get('/staff', [PublicController::class, 'staff'])->name('staff');
        Route::get('/societa/organigramma', [PublicController::class, 'organigramma'])->name('organigramma');
        Route::get('/sponsor', [PublicController::class, 'sponsor'])->name('sponsor');
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');

        // =============================================
        // Shop Routes — slugs localizzati
        // =============================================
        $shopSlugs = [
            'cerca'     => $loc === 'en' ? 'search' : 'cerca',
            'categoria' => $loc === 'en' ? 'category' : 'categoria',
            'prodotto'  => $loc === 'en' ? 'product' : 'prodotto',
            'carrello'  => $loc === 'en' ? 'cart' : 'carrello',
            'conferma'  => $loc === 'en' ? 'confirmed' : 'conferma',
            'annullato' => $loc === 'en' ? 'cancelled' : 'annullato',
            'ordine'    => $loc === 'en' ? 'order' : 'ordine',
            'ricevuta'  => $loc === 'en' ? 'receipt' : 'ricevuta',
            'ordini'    => $loc === 'en' ? 'orders' : 'ordini',
            'registrati'=> $loc === 'en' ? 'register' : 'registrati',
            'contatti'  => $loc === 'en' ? 'contacts' : 'contatti',
        ];

        Route::prefix('shop')->middleware([\App\Http\Middleware\TrackShopPageView::class])->group(function () use ($shopSlugs) {
            // Public shop pages
            Route::get('/', [\App\Http\Controllers\Shop\ShopController::class, 'index'])->name('shop');
            Route::get('/'.$shopSlugs['cerca'], [\App\Http\Controllers\Shop\ShopController::class, 'search'])->name('shop.search');
            Route::get('/'.$shopSlugs['categoria'].'/{category:slug}', [\App\Http\Controllers\Shop\ShopController::class, 'categoryShow'])->name('shop.category');
            Route::get('/'.$shopSlugs['prodotto'].'/{product:slug}', [\App\Http\Controllers\Shop\ShopController::class, 'productShow'])->name('shop.product');

            // Cart (web routes with CSRF)
            Route::get('/'.$shopSlugs['carrello'], [\App\Http\Controllers\Shop\CartController::class, 'index'])->name('shop.cart');
            Route::post('/'.$shopSlugs['carrello'], [\App\Http\Controllers\Shop\CartController::class, 'store'])->middleware('throttle:30,1')->name('shop.cart.store');
            Route::patch('/'.$shopSlugs['carrello'].'/{cartItem}', [\App\Http\Controllers\Shop\CartController::class, 'update'])->middleware('throttle:30,1')->name('shop.cart.update');
            Route::delete('/'.$shopSlugs['carrello'].'/{cartItem}', [\App\Http\Controllers\Shop\CartController::class, 'destroy'])->middleware('throttle:30,1')->name('shop.cart.destroy');
            Route::get('/'.$shopSlugs['carrello'].'/count', [\App\Http\Controllers\Shop\CartController::class, 'count'])->name('shop.cart.count');
            Route::get('/'.$shopSlugs['carrello'].'/data', [\App\Http\Controllers\Shop\CartController::class, 'data'])->name('shop.cart.data');

            // Checkout
            Route::get('/checkout', [\App\Http\Controllers\Shop\CheckoutController::class, 'show'])->name('shop.checkout');
            Route::post('/checkout', [\App\Http\Controllers\Shop\CheckoutController::class, 'store'])->middleware('throttle:5,1')->name('shop.checkout.store');
            Route::get('/checkout/'.$shopSlugs['conferma'].'/{orderToken}', [\App\Http\Controllers\Shop\CheckoutController::class, 'success'])->name('shop.checkout.success');
            Route::get('/checkout/'.$shopSlugs['annullato'].'/{orderToken}', [\App\Http\Controllers\Shop\CheckoutController::class, 'cancel'])->name('shop.checkout.cancel');

            // Order tracking (guest via token)
            Route::get('/'.$shopSlugs['ordine'].'/{orderNumber}', [\App\Http\Controllers\Shop\OrderController::class, 'show'])->name('shop.order.show');
            Route::get('/'.$shopSlugs['ordine'].'/{orderToken}/'.$shopSlugs['ricevuta'], [\App\Http\Controllers\Shop\OrderController::class, 'downloadReceipt'])->name('shop.order.receipt');

            // Auth-only shop routes
            Route::middleware('auth')->group(function () use ($shopSlugs) {
                Route::get('/'.$shopSlugs['ordini'], [\App\Http\Controllers\Shop\OrderController::class, 'index'])->name('shop.orders');
            });

            // Shop registration
            Route::middleware('guest')->group(function () use ($shopSlugs) {
                Route::get('/'.$shopSlugs['registrati'], [\App\Http\Controllers\Shop\ShopAuthController::class, 'showRegister'])->name('shop.register');
                Route::post('/'.$shopSlugs['registrati'], [\App\Http\Controllers\Shop\ShopAuthController::class, 'register'])->name('shop.register.store');
            });
        });
        Route::get('/'.$shopSlugs['contatti'], [PublicController::class, 'contatti'])->name('contatti');
        Route::post('/'.$shopSlugs['contatti'], [ContactController::class, 'submit'])->name('contatti.submit');
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
});


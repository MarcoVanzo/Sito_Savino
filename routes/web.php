<?php

use App\Http\Controllers\Admin\MetaOAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shop\PaymentVerificationController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ServeSocialCrawlerMeta;
use App\Http\Middleware\SetLocale;
use App\Services\SitemapBuilder;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/auth.php';

// Redirect 301 for the old WooCommerce subdomain
Route::domain('shop.savinodelbenevolley.it')->group(function () {
    Route::any('/{any?}', function ($any = null) {
        return redirect()->to(url('/shop/'.$any), 301);
    })->where('any', '.*');
});

// Sitemap generata a runtime (con cache): gli URL seguono APP_URL dell'ambiente.
// Non deve esistere un `public/sitemap.xml`, altrimenti il web server lo servirebbe
// al posto di questa rotta — ed è così che in produzione sono finiti in sitemap
// 378 URL `http://localhost:8000`.
Route::get('/sitemap.xml', function (SitemapBuilder $builder) {
    return response($builder->render(), 200, [
        'Content-Type' => 'application/xml; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('sitemap');

$locales = ['it', 'en'];

foreach ($locales as $loc) {
    $prefix = $loc === 'it' ? '' : $loc;
    $namePrefix = $loc === 'it' ? '' : "$loc.";

    Route::middleware([
        'throttle:web',
        ServeSocialCrawlerMeta::class,
        SetLocale::class.':'.$loc,
    ])->prefix($prefix)->name($namePrefix)->group(function () use ($loc, $namePrefix) {
        require __DIR__.'/pubbliche/sito.php';
        require __DIR__.'/pubbliche/shop.php';
    });
}

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', EnsureUserIsActive::class])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Collegamento OAuth con Meta
|--------------------------------------------------------------------------
|
| Meta rimanda l'utente su un URL fisso, quindi la callback non può vivere
| dentro una pagina Filament. Le rotte stanno sotto il prefisso del pannello
| per coerenza con l'URI dichiarato nell'app Meta, e restano protette da
| autenticazione: il controller verifica anche il ruolo.
|
*/
Route::middleware(['auth', EnsureUserIsActive::class])
    ->prefix('admin/social/meta')
    ->name('admin.social.meta.')
    ->group(function () {
        Route::get('/connect', [MetaOAuthController::class, 'connect'])->name('connect');
        Route::get('/callback', [MetaOAuthController::class, 'callback'])->name('callback');
    });

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    // Verifica metodo di pagamento (per aste)
    Route::get('/account/verifica-pagamento', [PaymentVerificationController::class, 'show'])->name('account.payment-verification');
    // Throttle stretto: la POST apre una sessione di pagamento verso Stripe,
    // senza limite sarebbe sfruttabile per card testing.
    Route::post('/account/verifica-pagamento', [PaymentVerificationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('account.payment-verification.store');
    Route::get('/account/verifica-pagamento/completata', [PaymentVerificationController::class, 'success'])->name('account.payment-verification.success');
});

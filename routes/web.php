<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ServeSocialCrawlerMeta;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

$locales = ['it', 'en'];

foreach ($locales as $loc) {
    $prefix = $loc === 'it' ? '' : $loc;
    $namePrefix = $loc === 'it' ? '' : "$loc.";

    Route::middleware([
        'throttle:web',
        ServeSocialCrawlerMeta::class,
        SetLocale::class.':'.$loc,
    ])->prefix($prefix)->name($namePrefix)->group(function () {
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
        Route::get('/shop', [PublicController::class, 'shop'])->name('shop');
        Route::get('/shop/checkout', [PublicController::class, 'shopCheckout'])->name('shop.checkout');
        Route::get('/contatti', [PublicController::class, 'contatti'])->name('contatti');
        Route::post('/contatti', [ContactController::class, 'submit'])->name('contatti.submit');
        Route::get('/in-costruzione', [PublicController::class, 'underConstruction'])->name('in-costruzione');

        // Rotta dinamica per le pagine del CMS (CATCH-ALL)
        Route::get('/{slug}', [PageController::class, 'show'])
            ->where('slug', '^(?!admin|api|filament|livewire|storage|_debugbar|_ignition|dashboard|profile|login|register|logout|forgot-password|reset-password|verify-email|confirm-password|email|password|stagione|risultati|gallery|staff|societa/organigramma|sponsor|news|shop|contatti|in-costruzione).*$')
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

require __DIR__.'/auth.php';

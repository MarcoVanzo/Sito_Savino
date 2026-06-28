<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/run-seeds-xyz-123', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\DatabaseSeeder',
            '--force' => true
        ]);
        $out1 = \Illuminate\Support\Facades\Artisan::output();

        \Illuminate\Support\Facades\Artisan::call('app:seed-menu-images');
        $out2 = \Illuminate\Support\Facades\Artisan::output();
        
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        $out3 = \Illuminate\Support\Facades\Artisan::output();

        return "<pre>Seed:\n$out1\n\nMenu:\n$out2\n\nCache:\n$out3</pre>";
    } catch (\Exception $e) {
        return "<pre>Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
    }
});

// Rotte Pubbliche SDB
Route::middleware('throttle:web')->group(function () {
    Route::get('/', [PublicController::class, 'home'])->name('home');
    Route::get('/stagione', [PublicController::class, 'stagione'])->name('stagione');
    Route::get('/stagione/b1', [PublicController::class, 'stagioneB1'])->name('stagione.b1');
    Route::get('/risultati', [PublicController::class, 'risultati'])->name('risultati');
    Route::get('/gallery', [PublicController::class, 'gallery'])->name('gallery');
    Route::get('/staff', [PublicController::class, 'staff'])->name('staff');
    Route::get('/organigramma', [PublicController::class, 'organigramma'])->name('organigramma');
    Route::get('/sponsor', [PublicController::class, 'sponsor'])->name('sponsor');
    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');
    Route::get('/shop', [PublicController::class, 'shop'])->name('shop');
    Route::get('/shop/checkout', [PublicController::class, 'shopCheckout'])->name('shop.checkout');
    Route::get('/contatti', [PublicController::class, 'contatti'])->name('contatti');
    Route::post('/contatti', [ContactController::class, 'submit'])->middleware('throttle:5,1')->name('contatti.submit');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', EnsureUserIsActive::class])->name('dashboard');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Rotta dinamica per le pagine del CMS (CATCH-ALL)
// DEVE essere l'ULTIMA route — intercetta tutto ciò che non è stato matchato sopra.
// Ignora percorsi di sistema noti.
Route::get('/{slug}', [PageController::class, 'show'])
    ->middleware('throttle:web')
    ->where('slug', '^(?!admin|api|filament|livewire|storage|_debugbar|_ignition|dashboard|profile|login|register|logout|forgot-password|reset-password|verify-email|confirm-password|email|password|stagione|risultati|gallery|staff|organigramma|sponsor|news|shop|contatti).*$')
    ->name('pages.show');

<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\PublicController;

use App\Http\Controllers\PageController;

// Rotte Pubbliche SDB
Route::middleware('throttle:web')->group(function () {
    Route::get('/', [PublicController::class, 'home'])->name('home');
    Route::get('/stagione', [PublicController::class, 'stagione'])->name('stagione');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', \App\Http\Middleware\EnsureUserIsActive::class])->name('dashboard');

Route::middleware(['auth', \App\Http\Middleware\EnsureUserIsActive::class])->group(function () {
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
    ->where('slug', '^(?!admin|api|filament|livewire|storage|_debugbar|_ignition|dashboard|profile|login|register|logout|forgot-password|reset-password|verify-email|confirm-password|email|password).*$')
    ->name('pages.show');

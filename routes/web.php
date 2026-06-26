<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\PublicController;

// Rotte Pubbliche SDB
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/stagione', [PublicController::class, 'stagione'])->name('stagione');
Route::get('/societa', function () { return Inertia::render('Public/Societa'); })->name('societa');
Route::get('/ticketing', function () { return Inertia::render('Public/Ticketing'); })->name('ticketing');
Route::get('/sponsor', function () { return Inertia::render('Public/Sponsor'); })->name('sponsor');
Route::get('/youth', function () { return Inertia::render('Public/Youth'); })->name('youth');
Route::get('/summer-camp', function () { return Inertia::render('Public/SummerCamp'); })->name('summer-camp');
Route::get('/sociale', function () { return Inertia::render('Public/Sociale'); })->name('sociale');
Route::get('/comunicazione', function () { return Inertia::render('Public/Comunicazione'); })->name('comunicazione');
Route::get('/shop', function () { return Inertia::render('Public/Shop'); })->name('shop');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

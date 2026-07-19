<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Merge carrello guest nel carrello utente (come nella registrazione shop)
        app(CartService::class)->mergeOnLogin($request->user());

        // Customer → shop, utenti con accesso al pannello → CMS, altri → dashboard.
        // In questo modo lo staff usa un'unica pagina di login (questa) e viene
        // portato direttamente nel pannello Filament.
        $role = $request->user()->role;

        if ($role === UserRole::Customer) {
            $default = route('shop', absolute: false);
        } elseif ($role->canAccessPanel()) {
            $default = '/admin';
        } else {
            $default = route('dashboard', absolute: false);
        }

        return redirect()->intended($default);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $isCustomer = $request->user()?->role === UserRole::Customer;

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Customer torna allo shop, altri alla home
        return redirect($isCustomer ? route('shop') : '/');
    }
}

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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
    public function store(LoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        // L'ID di sessione va catturato PRIMA del regenerate: il carrello ospite
        // è legato al vecchio ID e con quello nuovo non si troverebbe più.
        $guestSessionId = $request->session()->getId();

        $request->session()->regenerate();

        // Merge carrello guest nel carrello utente (come nella registrazione shop)
        app(CartService::class)->mergeOnLogin($request->user(), $guestSessionId);

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

        $target = $request->session()->pull('url.intended', $default);

        // Il pannello Filament non è una pagina Inertia: con un redirect normale
        // il client Inertia lo seguirebbe via XHR, riceverebbe HTML dove attende
        // JSON e mostrerebbe il modale d'errore con il pannello sovrapposto alla
        // pagina di login. Inertia::location forza una navigazione full-page e
        // resta un redirect normale per le richieste non-Inertia.
        if (str_starts_with(parse_url($target, PHP_URL_PATH) ?: '/', '/admin')) {
            return Inertia::location($target);
        }

        return redirect()->to($target);
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

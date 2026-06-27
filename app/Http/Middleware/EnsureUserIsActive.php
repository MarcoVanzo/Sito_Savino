<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Verifica che l'utente autenticato sia attivo.
     * Se is_active è false, lo disconnette e lo rimanda al login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! $request->user()->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Il tuo account non è ancora stato attivato. Contatta l\'amministratore.',
            ]);
        }

        return $next($request);
    }
}

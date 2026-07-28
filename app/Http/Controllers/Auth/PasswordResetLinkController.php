<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Il link viene inviato solo se l'indirizzo corrisponde a un utente,
        // ma la risposta è SEMPRE la stessa: restituire l'errore
        // `passwords.user` per un'email inesistente permetteva di enumerare
        // gli account registrati semplicemente provando indirizzi diversi.
        //
        // Gli altri esiti (throttling incluso) sono deliberatamente indistinguibili
        // per lo stesso motivo; il rate limiting resta attivo lato Password broker.
        Password::sendResetLink(
            $request->only('email')
        );

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}

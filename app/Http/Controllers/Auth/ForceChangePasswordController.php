<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ForceChangePasswordController extends Controller
{
    /**
     * Show the force change password form.
     */
    public function show(Request $request)
    {
        // Se l'utente ha già cambiato la password, reindirizzalo alla sua destinazione corretta
        if (! $request->user()->must_change_password) {
            $default = $request->user()->role->canAccessPanel() ? '/admin' : '/dashboard';
            return redirect()->to($default);
        }

        return Inertia::render('Auth/ForceChangePassword');
    }

    /**
     * Update the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Validiamo la password corrente, la nuova password e la sua conferma
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // Verifica di sicurezza aggiuntiva: la nuova password deve essere diversa da quella attuale
        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'La nuova password deve essere diversa da quella attuale.',
            ]);
        }

        // Il Model User ha il cast 'hashed' sul campo password,
        // quindi l'assegnazione semplice attiva l'hashing automatico
        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        $default = $user->role->canAccessPanel() ? '/admin' : '/dashboard';

        return redirect()->intended($default);
    }
}

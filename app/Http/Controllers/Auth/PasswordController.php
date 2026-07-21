<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\NotAPreviousPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     *
     * Il Model User ha il cast 'hashed' sul campo password,
     * quindi NON usare Hash::make() — il cast lo fa automaticamente.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                Password::defaults(),
                'confirmed',
                new NotAPreviousPassword($request->user()),
            ],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        // L'hash in sessione non va aggiornato a mano: AuthenticateSession, ora
        // attivo sull'intero gruppo web oltre che sul pannello, lo riscrive al
        // termine della richiesta nel proprio formato (hashPasswordForCookie),
        // così la sessione corrente resta valida mentre le altre decadono.

        return back();
    }
}

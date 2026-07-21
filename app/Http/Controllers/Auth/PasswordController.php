<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\NotAPreviousPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Mantiene viva la sessione corrente dopo il cambio password: il
        // middleware AuthenticateSession del pannello Filament forzerebbe il
        // logout se l'hash salvato in sessione non fosse aggiornato.
        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $request->user()->getAuthPassword()
        );

        return back();
    }
}

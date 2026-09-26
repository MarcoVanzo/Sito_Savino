<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\DatiDelCliente;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Cancella l'account dalla pagina di Breeze (`/profile`).
     *
     * Passa dalle stesse regole di `/shop/account` (AccountController):
     * prima faceva `$user->delete()` e basta, quindi un redattore poteva
     * cancellarsi da qui, chi aveva un'asta in corso lasciava l'offerta senza
     * nessuno dietro, e restavano dati nel registro, gli ordini senza
     * recapito e il cliente su Stripe (DatiDelCliente::cancella).
     */
    public function destroy(Request $request, DatiDelCliente $dati): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($motivo = $dati->motivoPerNonCancellare($user)) {
            return back()->withErrors(['password' => $motivo]);
        }

        $id = $user->id;

        Auth::logout();

        $dati->cancella($user);

        Log::channel('daily')->info('Account cancellato su richiesta', ['user_id' => $id]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

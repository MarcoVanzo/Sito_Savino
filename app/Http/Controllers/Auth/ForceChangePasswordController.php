<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NotAPreviousPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ForceChangePasswordController extends Controller
{
    /**
     * Show the force change password form.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Nulla da fare se la password non è né da cambiare al primo accesso né scaduta.
        if ($user->must_change_password === false && ! $user->passwordHasExpired()) {
            return $this->redirectAfterChange($this->defaultDestination($user));
        }

        return Inertia::render('Auth/ForceChangePassword', [
            // Distingue i due casi nel testo della pagina: primo accesso vs scadenza.
            'reason' => $user->must_change_password ? 'first_login' : 'expired',
            'expiredOn' => $user->passwordExpiresAt()?->toDateString(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function store(Request $request): SymfonyResponse
    {
        $user = $request->user();

        // Validiamo la password corrente, la nuova password e la sua conferma.
        // NotAPreviousPassword copre anche il caso "uguale a quella attuale".
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', new NotAPreviousPassword($user)],
        ]);

        // Il Model User ha il cast 'hashed' sul campo password,
        // quindi l'assegnazione semplice attiva l'hashing automatico
        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        // Rinfresca l'hash della password memorizzato in sessione.
        // Il pannello Filament usa il middleware AuthenticateSession, che al
        // primo accesso a /admin confronta l'hash salvato in sessione con
        // quello dell'utente: senza questo aggiornamento troverebbe l'hash
        // vecchio (questa rotta non passa da AuthenticateSession), forzerebbe
        // il logout e reindirizzerebbe alla pagina di login di Filament.
        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $user->getAuthPassword()
        );

        return $this->redirectAfterChange(
            $request->session()->pull('url.intended', $this->defaultDestination($user))
        );
    }

    /**
     * Dove mandare l'utente dopo il cambio: pannello per lo staff, shop per i
     * clienti, dashboard per gli altri.
     */
    private function defaultDestination(User $user): string
    {
        if ($user->role === UserRole::Customer) {
            return route('shop', absolute: false);
        }

        return $user->role->canAccessPanel() ? '/admin' : '/dashboard';
    }

    /**
     * Reindirizza l'utente dopo il cambio password.
     *
     * Il pannello Filament NON è una pagina Inertia. Con un redirect normale il
     * client Inertia seguirebbe la destinazione via XHR, riceverebbe HTML dove
     * attende JSON e mostrerebbe il proprio modale d'errore con il pannello
     * incastrato sopra la pagina di cambio password (schermata "doppia").
     *
     * Inertia::location risponde invece 409 + X-Inertia-Location, che il client
     * traduce in una navigazione full-page; per le richieste non-Inertia resta
     * un normale redirect, quindi è sicuro usarlo in entrambi i casi.
     */
    private function redirectAfterChange(string $target): SymfonyResponse
    {
        $path = parse_url($target, PHP_URL_PATH) ?: '/';

        return str_starts_with($path, '/admin')
            ? Inertia::location($target)
            : redirect()->to($target);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! $this->deveCambiareLaPassword($request->user())) {
            return $next($request);
        }

        // Evita di bloccare le chiamate API (che non possono seguire un
        // redirect verso una pagina). NON si guarda `expectsJson()`: è
        // pilotato dall'header Accept del client, quindi basterebbe
        // inviare `Accept: application/json` per navigare tutto il sito
        // senza mai cambiare la password scaduta.
        if ($request->is('api/*') || $this->eUnaRottaConsentita($request)) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }

    /**
     * L'utente deve passare da /change-password: o perche' e' il primo accesso,
     * o perche' la password e' scaduta (policy: ogni N mesi).
     */
    private function deveCambiareLaPassword(mixed $user): bool
    {
        if (array_key_exists('must_change_password', $user->getAttributes()) && $user->must_change_password) {
            return true;
        }

        return $user instanceof User && $user->passwordHasExpired();
    }

    /**
     * Le uniche rotte raggiungibili con la password da cambiare: quelle del
     * cambio stesso, l'uscita, e le richieste che non sono pagine.
     *
     * Il confronto sull'uscita e' ESATTO sulle due sole rotte esistenti (sito
     * pubblico e pannello Filament). Un `endsWith($path, 'logout')` lasciava
     * passare qualunque URL il cui ultimo segmento finisse per "logout":
     * bastava uno slug CMS o di prodotto tipo `/shop/prodotto/kit-logout` per
     * navigare il sito senza mai cambiare la password scaduta.
     */
    private function eUnaRottaConsentita(Request $request): bool
    {
        $routeName = $request->route()?->getName();
        $path = $request->path();

        if (in_array($routeName, ['password.change', 'password.change.update'], true)) {
            return true;
        }

        if (in_array($routeName, ['logout', 'filament.admin.auth.logout'], true)
            || in_array($path, ['logout', 'admin/logout'], true)) {
            return true;
        }

        return Str::startsWith($path, ['_debugbar', '_ignition', 'storage', 'livewire', 'vendor']);
    }
}

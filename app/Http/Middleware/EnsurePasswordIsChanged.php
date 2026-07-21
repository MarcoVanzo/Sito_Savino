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
        if (Auth::check()) {
            $user = $request->user();
            $mustChange = array_key_exists('must_change_password', $user->getAttributes()) && $user->must_change_password;

            // Password scaduta (policy: ogni N mesi) ⇒ stesso trattamento del
            // cambio obbligatorio al primo accesso: si passa da /change-password.
            if (! $mustChange && $user instanceof User && $user->passwordHasExpired()) {
                $mustChange = true;
            }

            if ($mustChange) {
                // Evita di bloccare le chiamate API (che non possono seguire un
                // redirect verso una pagina). NON si guarda `expectsJson()`: è
                // pilotato dall'header Accept del client, quindi basterebbe
                // inviare `Accept: application/json` per navigare tutto il sito
                // senza mai cambiare la password scaduta.
                if ($request->is('api/*')) {
                    return $next($request);
                }

                $route = $request->route();
                $routeName = $route ? $route->getName() : null;
                $path = $request->path();

                // Consentiamo l'accesso solo alle rotte di cambio password e alle rotte di logout
                // per evitare redirect loop infiniti. Escludiamo anche asset e chiamate di debug.
                if ($routeName !== 'password.change' &&
                    $routeName !== 'password.change.update' &&
                    // `contains` lascerebbe passare qualsiasi URL con "logout"
                    // dentro (es. uno slug di prodotto): serve il segmento finale.
                    ! Str::endsWith($path, 'logout') &&
                    ! Str::startsWith($path, ['_debugbar', '_ignition', 'storage', 'livewire', 'vendor'])
                ) {
                    return redirect()->route('password.change');
                }
            }
        }

        return $next($request);
    }
}

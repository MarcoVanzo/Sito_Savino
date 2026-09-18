<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Chiude il sito dietro una basic auth finché è in anteprima.
 *
 * La protezione si accende con `PREVIEW_AUTH_ENABLED`, non deducendola dalla
 * presenza delle credenziali. Prima bastava che `PREVIEW_AUTH_USER` o
 * `PREVIEW_AUTH_PASS` arrivassero vuote perché il middleware lasciasse passare
 * chiunque, in silenzio: dal 21/07/2026 al 18/09/2026 i due segreti erano
 * dichiarati nello spec di App Platform ma a runtime risultavano vuoti, e il
 * sito ha risposto 200 a tutti senza che una riga di log lo dicesse. Un
 * segreto che non arriva è un guasto, e un guasto non deve assomigliare a una
 * configurazione riuscita.
 *
 * Con la protezione accesa e le credenziali mancanti si risponde 503: il sito
 * resta chiuso, che è il verso giusto in cui sbagliare quando l'intenzione
 * dichiarata è tenerlo chiuso.
 */
class PreviewBasicAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('services.preview.enabled')) {
            return $next($request);
        }

        $user = (string) config('services.preview.user');
        $pass = (string) config('services.preview.pass');

        if ($user === '' || $pass === '') {
            Log::error('PreviewBasicAuth: protezione richiesta ma credenziali assenti a runtime.', [
                'user_impostato' => $user !== '',
                'pass_impostata' => $pass !== '',
            ]);

            return response('Anteprima non configurata', 503);
        }

        // Entrambi i confronti si valutano sempre: in `||` il secondo verrebbe
        // saltato appena il primo fallisce, e il tempo di risposta direbbe se a
        // sbagliare è stato il nome utente o la password.
        $utenteOk = hash_equals($user, $request->getUser() ?? '');
        $passwordOk = hash_equals($pass, $request->getPassword() ?? '');

        if (! $utenteOk || ! $passwordOk) {
            return response('Non autorizzato', 401, ['WWW-Authenticate' => 'Basic']);
        }

        return $next($request);
    }
}

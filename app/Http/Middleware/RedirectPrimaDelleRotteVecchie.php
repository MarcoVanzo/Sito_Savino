<?php

namespace App\Http\Middleware;

use App\Models\IndirizzoDaMappare;
use App\Support\RedirectGestiti;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Il redirect deciso a mano vince sulle regole generali.
 *
 * Sta sulle rotte di `legacy.php` e sulla rotta generica `/{slug}`, non fra i
 * middleware globali: così la tabella si consulta solo per gli indirizzi che
 * possono essere del vecchio sito, e mai per le pagine vere. Gli indirizzi che
 * nessuna rotta riconosce passano dal gestore dei 404 (`bootstrap/app.php`).
 *
 * Quando a rispondere è una rotta generica (`/tag/{any}` → `/news`) la visita
 * finisce nei «404 da mappare»: non è un errore, ma il vecchio indirizzo non
 * arriva al suo contenuto.
 */
class RedirectPrimaDelleRotteVecchie
{
    public function handle(Request $request, Closure $next): Response
    {
        $deciso = RedirectGestiti::rispostaPer($request);

        if ($deciso !== null) {
            return $deciso;
        }

        /** @var Response $risposta */
        $risposta = $next($request);

        if ($risposta->isRedirection() && RedirectGestiti::rottaGenerica($request)) {
            $percorso = RedirectGestiti::normalizza($request->getRequestUri());

            if ($percorso !== null) {
                RedirectGestiti::registraMancante($percorso, IndirizzoDaMappare::ORIGINE_LEGACY);
            }
        }

        return $risposta;
    }
}

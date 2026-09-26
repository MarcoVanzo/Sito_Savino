<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'IP del visitatore, preso dall'header che scrive DigitalOcean.
 *
 * Dietro App Platform REMOTE_ADDR è il proxy, e DigitalOcean non pubblica i
 * suoi indirizzi: per questo i proxy fidati sono `*`. Fidarsi anche di
 * X-Forwarded-For però voleva dire prendere l'IP da un header che il client
 * scrive a piacere, e ogni limite per IP (login, reset password, recesso,
 * newsletter, diagnostica) si aggirava cambiandolo a ogni richiesta.
 *
 * `DO-Connecting-IP` invece lo scrive il bordo di App Platform con l'indirizzo
 * da cui è arrivata la connessione, sovrascrivendo quello mandato dal client.
 * Qui diventa REMOTE_ADDR e X-Forwarded-For si scarta: con i proxy fidati `*`
 * Symfony preferirebbe quello, e il client lo scrive a piacere.
 *
 * Senza l'header (locale, test, sonda interna, o se App Platform smettesse di
 * mandarlo) resta il comportamento di prima: l'IP da X-Forwarded-For. Meglio
 * un limite aggirabile che tutti i visitatori sullo stesso contatore
 * dell'IP del proxy, cioè 429 a raffica sul login. Gira prima di tutto il
 * resto, perché i limiter devono già vedere l'IP giusto.
 */
class UsaLIpDelClienteDiDigitalOcean
{
    public const HEADER = 'DO-Connecting-IP';

    public function handle(Request $request, Closure $next): Response
    {
        $ip = trim((string) $request->headers->get(self::HEADER, ''));

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            $request->server->set('REMOTE_ADDR', $ip);
            $request->headers->remove('X-Forwarded-For');
            $request->server->remove('HTTP_X_FORWARDED_FOR');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Due pulizie chieste dal parere del 5 ottobre 2026, su ogni risposta.
 *
 * - **Soglie dei limiti**: `throttle` scrive `X-RateLimit-Limit` e
 *   `X-RateLimit-Remaining`, cioè quante richieste si possono fare e quante ne
 *   restano: a chi prova a forzare un modulo dicono quanto rallentare. Sul 429
 *   resta `Retry-After`, che è ciò che serve a un client onesto.
 *   (`x-do-app-origin` lo aggiunge l'edge di App Platform dopo di noi: dal
 *   codice non si toglie.)
 * - **Cookie del vecchio sito**: i browser di chi visitava il WordPress
 *   portano ancora `wp-settings-*`, `pys_*` (PixelYourSite) e
 *   `cookieyes-consent`. Il sito non li legge, ma restano lì e la scansione
 *   dei cookie li vede: a chi li porta si mandano scaduti, nelle due forme
 *   (host e dominio) in cui il vecchio sito poteva averli scritti. Dal 1°
 *   aprile 2027 nessuno dovrebbe più averli: allora la parte si può togliere.
 *
 * Globale, in coda: vale anche per le risposte servite dalla cache pubblica e
 * i cookie scaduti escono in chiaro, fuori da EncryptCookies.
 */
class RispostaSenzaResidui
{
    /** @var list<string> */
    private const HEADER_DA_TOGLIERE = ['X-RateLimit-Limit', 'X-RateLimit-Remaining'];

    /** Nomi (o prefissi, se finiscono con `*`) dei cookie del vecchio sito. */
    private const COOKIE_DEL_VECCHIO_SITO = ['wp-settings-*', 'wp-settings-time-*', 'pys_*', 'cookieyes-consent', 'last_pys_*'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADER_DA_TOGLIERE as $header) {
            $response->headers->remove($header);
        }

        foreach (array_keys($request->cookies->all()) as $nome) {
            if (self::delVecchioSito((string) $nome)) {
                $this->faiScadere($response, (string) $nome, $request->getHost());
            }
        }

        return $response;
    }

    public static function delVecchioSito(string $nome): bool
    {
        foreach (self::COOKIE_DEL_VECCHIO_SITO as $modello) {
            if (str_ends_with($modello, '*') ? str_starts_with($nome, rtrim($modello, '*')) : $nome === $modello) {
                return true;
            }
        }

        return false;
    }

    private function faiScadere(Response $response, string $nome, string $host): void
    {
        $dominio = preg_replace('/^www\./', '', $host);

        // HttpOnly non conta per farlo scadere (il browser confronta nome,
        // dominio e percorso) e toglie a SonarCloud il dubbio (php:S3330).
        foreach ([null, '.'.$dominio] as $ambito) {
            $response->headers->setCookie(new Cookie($nome, '', 1, '/', $ambito, true, true, false, Cookie::SAMESITE_LAX));
        }
    }
}

<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * La rotta che risponde a un percorso, senza toccare la richiesta in corso.
 *
 * `RouteCollection::match()` lega i parametri sull'oggetto Route condiviso:
 * se il percorso cercato e la richiesta corrente passano dalla stessa rotta
 * (`/societa/palazzetto` cercato dal menu mentre si serve `/societa/storia`),
 * il parametro `slug` della richiesta diventa quello cercato. Finché il menu
 * si calcolava dopo il controller non si vedeva; calcolato prima (le briciole
 * dei dati strutturati) il controller cercava la pagina sbagliata e rispondeva
 * 404. Qui i parametri della rotta corrente si rimettono com'erano.
 */
class RottaDelPercorso
{
    /**
     * @throws HttpException se nessuna rotta risponde al percorso
     */
    public static function trova(string $percorso): Route
    {
        $corrente = request()->route();
        $parametri = $corrente instanceof Route && $corrente->hasParameters() ? $corrente->parameters() : null;

        try {
            return app('router')->getRoutes()->match(Request::create($percorso, 'GET'));
        } finally {
            if ($corrente instanceof Route && $parametri !== null) {
                foreach ($parametri as $nome => $valore) {
                    $corrente->setParameter($nome, $valore);
                }
            }
        }
    }
}

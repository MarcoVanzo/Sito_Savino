<?php

namespace Tests\Feature;

use App\Support\RottaDelPercorso;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `match()` lega i parametri sull'oggetto Route condiviso: cercando
 * `/societa/palazzetto` mentre si serve `/societa/storia` la rotta trovata e
 * quella della richiesta sono lo stesso oggetto. Rimettendo a posto la
 * richiesta si cambiava anche il risultato, e il menu (in cache per un giorno)
 * mandava ogni voce della sezione alla pagina aperta.
 */
class RottaDelPercorsoNonConfondeLeRichiesteTest extends TestCase
{
    #[Test]
    public function la_rotta_trovata_ha_i_parametri_del_percorso_cercato_e_la_richiesta_i_suoi(): void
    {
        $richiesta = Request::create('/societa/storia', 'GET');
        $corrente = app('router')->getRoutes()->match($richiesta);
        $richiesta->setRouteResolver(fn () => $corrente);
        $this->app->instance('request', $richiesta);

        $trovata = RottaDelPercorso::trova('/societa/palazzetto');

        $this->assertSame('societa.page', $trovata->getName());
        $this->assertSame('palazzetto', $trovata->parameter('slug'));
        $this->assertSame('palazzetto', $trovata->originalParameter('slug'));

        $this->assertSame('storia', $corrente->parameter('slug'));
        $this->assertSame('storia', $corrente->originalParameter('slug'));
    }
}

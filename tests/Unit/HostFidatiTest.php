<?php

namespace Tests\Unit;

use App\Support\HostFidati;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * In produzione gli URL generati prendono la radice da APP_URL (forceRootUrl
 * in AppServiceProvider), mai dall'Host della richiesta.
 */
class HostFidatiTest extends TestCase
{
    #[Test]
    public function la_radice_pubblica_e_sempre_https_sull_host_di_app_url(): void
    {
        $this->assertSame('https://savinodelbenevolley.it', HostFidati::radicePubblica('https://savinodelbenevolley.it'));
        $this->assertSame('https://savinodelbenevolley.it', HostFidati::radicePubblica('http://savinodelbenevolley.it/'));
        $this->assertSame('https://seashell-app.ondigitalocean.app', HostFidati::radicePubblica('seashell-app.ondigitalocean.app'));
        $this->assertSame('https://localhost:8443', HostFidati::radicePubblica('https://localhost:8443'));
        $this->assertNull(HostFidati::radicePubblica(''));
    }
}

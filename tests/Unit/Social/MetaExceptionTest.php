<?php

namespace Tests\Unit\Social;

use App\Services\Social\MetaException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Su questa classificazione si decide se ritentare.
 *
 * Sbagliarla costa in due modi opposti: ritentare su un rate limit raddoppia le
 * chiamate proprio mentre Meta sta chiudendo il rubinetto; non ritentare su una
 * metrica rifiutata lascia la serie a zero per sempre, senza che nessun errore
 * arrivi in pagina.
 */
class MetaExceptionTest extends TestCase
{
    #[Test]
    public function riconosce_token_scaduto_rate_limit_e_permessi(): void
    {
        $this->assertSame('token_expired', MetaException::fromGraphError(['code' => 190], 401)->reason);
        $this->assertSame('rate_limited', MetaException::fromGraphError(['code' => 4], 400)->reason);
        $this->assertSame('rate_limited', MetaException::fromGraphError(['code' => 32], 400)->reason);
        $this->assertSame('permission', MetaException::fromGraphError(['code' => 200], 403)->reason);
        $this->assertSame('invalid_metric', MetaException::fromGraphError(['code' => 100], 400)->reason);
        $this->assertSame('unavailable', MetaException::fromGraphError([], 500)->reason);
    }

    #[Test]
    public function si_ritenta_solo_togliendo_la_metrica_rifiutata(): void
    {
        $this->assertTrue(MetaException::fromGraphError(['code' => 100], 400)->isRetryableWithoutMetric());

        // Ritentare qui significherebbe solo consumare chiamate.
        $this->assertFalse(MetaException::fromGraphError(['code' => 4], 400)->isRetryableWithoutMetric());
        $this->assertFalse(MetaException::fromGraphError(['code' => 190], 401)->isRetryableWithoutMetric());
    }

    #[Test]
    public function il_messaggio_dice_cosa_fare(): void
    {
        $this->assertStringContainsString(
            'ricollega',
            mb_strtolower(MetaException::fromGraphError(['code' => 190], 401)->userMessage()),
        );
    }
}

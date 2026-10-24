<?php

namespace Tests\Unit\Support;

use App\Models\ConsensoCookie;
use App\Support\TestiDelleInformative;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La versione di un'informativa è la data scritta nel suo testo, e quella della
 * Cookie Policy è anche la versione del consenso ai cookie.
 *
 * Il 2 ottobre 2026 la Cookie Policy è cambiata («È aggiornata al 2 ottobre»)
 * ma la versione del consenso era una costante a parte, rimasta al 26
 * settembre: chi aveva già scelto non ha rivisto il banner, contro ciò che la
 * policy stessa promette. Ora la versione è una sola e questo test la tiene
 * allineata al testo.
 */
class VersioniDelleInformativeTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function informative(): array
    {
        return [
            'privacy-policy' => ['privacy-policy'],
            'cookie-policy' => ['cookie-policy'],
        ];
    }

    #[DataProvider('informative')]
    #[Test]
    public function la_versione_e_la_data_scritta_nel_testo_in_tutte_le_lingue(string $slug): void
    {
        $versione = TestiDelleInformative::versione($slug);

        $this->assertNotNull($versione, "{$slug} non dichiara la sua versione.");

        $data = Carbon::createFromFormat('Y-m-d', $versione);
        $contenuto = TestiDelleInformative::contenuto($slug);

        $this->assertStringContainsString('aggiornata al '.$data->locale('it')->translatedFormat('j F Y'), $contenuto['it']);
        $this->assertStringContainsString('Last updated '.$data->locale('en')->translatedFormat('j F Y'), $contenuto['en']);
    }

    #[Test]
    public function il_consenso_ai_cookie_segue_la_versione_della_cookie_policy(): void
    {
        $this->assertSame(TestiDelleInformative::versione('cookie-policy'), ConsensoCookie::versione());
    }
}

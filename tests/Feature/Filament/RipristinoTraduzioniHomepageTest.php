<?php

namespace Tests\Feature\Filament;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un salvataggio della pagina Impostazioni → Homepage ha riscritto nove voci
 * come testo semplice, portandosi via l'inglese. Qui si verifica che il
 * ripristino rimetta le traduzioni tenendo l'italiano che c'è adesso — nel
 * frattempo il claim è cambiato — e che non tocchi ciò che è già a posto.
 */
class RipristinoTraduzioniHomepageTest extends TestCase
{
    use RefreshDatabase;

    private function ripristina(): void
    {
        (require database_path('migrations/2026_08_20_170000_ripristina_le_traduzioni_inglesi_della_homepage.php'))->up();
    }

    #[Test]
    public function rimette_l_inglese_tenendo_l_italiano_attuale(): void
    {
        SiteSetting::set('cta_shop_title', 'Shop Ufficiale');

        $this->ripristina();

        $this->assertSame(
            ['it' => 'Shop Ufficiale', 'en' => 'Official Shop'],
            SiteSetting::perLocale('cta_shop_title'),
        );
    }

    /**
     * "BELIEVE" è già inglese: per il claim le due lingue coincidono, e il
     * valore nuovo scritto in redazione non va sovrascritto con quello vecchio.
     */
    #[Test]
    public function il_claim_vale_uguale_nelle_due_lingue(): void
    {
        SiteSetting::set('hero_tagline', 'BELIEVE');

        $this->ripristina();

        $this->assertSame(
            ['it' => 'BELIEVE', 'en' => 'BELIEVE'],
            SiteSetting::perLocale('hero_tagline'),
        );
    }

    #[Test]
    public function non_tocca_una_voce_gia_tradotta(): void
    {
        SiteSetting::set('stats_title', ['it' => 'I nostri numeri', 'en' => 'Our numbers']);

        $this->ripristina();

        $this->assertSame(
            ['it' => 'I nostri numeri', 'en' => 'Our numbers'],
            SiteSetting::perLocale('stats_title'),
        );
    }
}

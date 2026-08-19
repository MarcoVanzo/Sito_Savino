<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsSite;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il Measurement ID deve arrivare al front-end, altrimenti il tag non si carica
 * e non si misura niente.
 *
 * È un guasto che non si vede: la pagina pubblica funziona identica, il
 * pannello mostra il campo compilato, e ci si accorge del buco solo settimane
 * dopo guardando un grafico vuoto. Il punto delicato è che
 * `SiteSetting::getAllGrouped()` raggruppa sulla colonna `group`: una riga
 * salvata nel gruppo sbagliato è invisibile al sito pubblico.
 */
class MeasurementTagTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_configurazione_iniziale_e_gia_in_archivio(): void
    {
        // La migrazione di configurazione porta con sé i due valori: senza,
        // ogni ambiente andrebbe compilato a mano dopo il deploy.
        $this->assertSame('G-MZ6MT5576Y', SiteSetting::query()->where('key', 'ga4_measurement_id')->value('value'));
        $this->assertSame('analytics', SiteSetting::query()->where('key', 'ga4_measurement_id')->value('group'));

        $site = AnalyticsSite::query()->firstOrFail();
        $this->assertSame('550742878', $site->property_id);
    }

    #[Test]
    public function il_measurement_id_arriva_al_front_end(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('siteSettings.analytics.ga4_measurement_id', 'G-MZ6MT5576Y')
            );
    }

    #[Test]
    public function il_pixel_di_meta_arriva_al_front_end_con_la_regola_sul_consenso(): void
    {
        config()->set('services.meta.pixel_requires_consent', false);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('siteSettings.analytics.meta_pixel_id', '2048882385693445')
                // Il front-end deve sapere se subordinare il pixel al consenso:
                // è una configurazione d'ambiente, non un'impostazione del pannello.
                ->where('siteSettings.analytics.meta_pixel_requires_consent', false)
            );
    }

    #[Test]
    public function la_regola_sul_consenso_del_pixel_segue_la_configurazione(): void
    {
        config()->set('services.meta.pixel_requires_consent', true);

        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('siteSettings.analytics.meta_pixel_requires_consent', true)
            );
    }

    #[Test]
    public function senza_measurement_id_il_front_end_non_riceve_nulla(): void
    {
        SiteSetting::set('ga4_measurement_id', '');

        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('siteSettings.analytics.ga4_measurement_id', '')
            );
    }
}

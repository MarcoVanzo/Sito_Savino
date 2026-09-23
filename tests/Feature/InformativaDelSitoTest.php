<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Del sito c'è una informativa sola, ed è la pagina.
 *
 * Il footer chiedeva prima il PDF caricato in Impostazioni → Documenti Legali e
 * ripiegava sulla pagina solo se mancava: i PDF c'erano, quindi da ogni pagina
 * il link "Privacy Policy" apriva l'informativa del vecchio sito WordPress
 * mentre il banner dei cookie e le caselle dei moduli facevano accettare
 * l'altra. Qui si verifica che quelle due chiavi non tornino, e che il
 * documento promozionale — che è un'altra cosa e resta — non le riprenda.
 */
class InformativaDelSitoTest extends TestCase
{
    use RefreshDatabase;

    private function eseguiLaMigrazione(): void
    {
        (require database_path('migrations/2026_09_23_140000_una_sola_informativa_per_il_sito.php'))->up();
    }

    private function impostazione(string $chiave, string $valore, ?string $gruppo = 'legal'): void
    {
        DB::table('site_settings')->insert([
            'key' => $chiave,
            'value' => $valore,
            'group' => $gruppo,
            'type' => 'text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        SiteSetting::clearCache();
    }

    private function legal(): array
    {
        SiteSetting::clearCache();

        return SiteSetting::getAllGrouped()['legal'] ?? [];
    }

    public function test_il_pdf_promozionale_prende_il_nome_che_gli_spetta(): void
    {
        $this->impostazione('privacy_policy', 'legal/Informativa generale Privacy.pdf');

        $this->eseguiLaMigrazione();

        $legal = $this->legal();

        $this->assertSame('legal/Informativa generale Privacy.pdf', $legal['informativa_promozionale'] ?? null);
        $this->assertArrayNotHasKey('privacy_policy', $legal);
    }

    public function test_l_informativa_cookie_del_vecchio_sito_se_ne_va(): void
    {
        $this->impostazione('cookie_policy', 'legal/Informativa Cookie.pdf');

        $this->eseguiLaMigrazione();

        $this->assertArrayNotHasKey('cookie_policy', $this->legal());
    }

    public function test_riconosce_anche_la_chiave_scritta_per_esteso(): void
    {
        // Le due forme convivono: `group` = 'legal' con chiave nuda, oppure
        // chiave `legal.x` nel gruppo predefinito. In produzione e in locale
        // non è la stessa.
        $this->impostazione('legal.privacy_policy', 'legal/Informativa generale Privacy.pdf', 'general');
        $this->impostazione('legal.cookie_policy', 'legal/Informativa Cookie.pdf', 'general');

        $this->eseguiLaMigrazione();

        $legal = $this->legal();

        $this->assertSame('legal/Informativa generale Privacy.pdf', $legal['informativa_promozionale'] ?? null);
        $this->assertArrayNotHasKey('privacy_policy', $legal);
        $this->assertArrayNotHasKey('cookie_policy', $legal);
    }

    public function test_non_passa_sopra_a_una_scelta_gia_fatta(): void
    {
        $this->impostazione('informativa_promozionale', 'legal/Scelta-dalla-redazione.pdf');
        $this->impostazione('privacy_policy', 'legal/Informativa generale Privacy.pdf');

        $this->eseguiLaMigrazione();

        $legal = $this->legal();

        $this->assertSame('legal/Scelta-dalla-redazione.pdf', $legal['informativa_promozionale'] ?? null);
        $this->assertArrayNotHasKey('privacy_policy', $legal);
    }

    public function test_si_puo_rieseguire(): void
    {
        $this->impostazione('privacy_policy', 'legal/Informativa generale Privacy.pdf');

        $this->eseguiLaMigrazione();
        $dopoLaPrima = $this->legal();

        $this->eseguiLaMigrazione();

        $this->assertSame($dopoLaPrima, $this->legal());
    }
}

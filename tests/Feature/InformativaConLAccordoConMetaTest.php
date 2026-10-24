<?php

namespace Tests\Feature;

use App\Support\TestiDelleInformative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Privacy Policy del 20 ottobre 2026: Meta contitolare per il pixel, con il
 * link al Controller Addendum che contiene il contenuto essenziale
 * dell'accordo (art. 26 §2 GDPR).
 */
class InformativaConLAccordoConMetaTest extends TestCase
{
    use RefreshDatabase;

    private function pubblica(array $contenuto): void
    {
        DB::table('pages')->where('slug', 'privacy-policy')->delete();
        DB::table('pages')->insert([
            'slug' => 'privacy-policy',
            'title' => json_encode(['it' => 'Privacy Policy', 'en' => 'Privacy Policy']),
            'template' => 'Public/ContentPage',
            'status' => 'publish',
            'content' => json_encode($contenuto, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function migra(): void
    {
        (require database_path('migrations/2026_10_20_120000_l_informativa_linka_l_accordo_con_meta.php'))->up();
    }

    #[Test]
    public function la_versione_del_cinque_ottobre_prende_il_link_al_controller_addendum(): void
    {
        $this->pubblica([
            'it' => '<p>È aggiornata al 5 ottobre 2026.</p><p>Meta è contitolare (articolo 26 del GDPR) e titolare autonomo di ciò che ne fa dopo. Non vendiamo e non cediamo i dati a nessun altro.</p>',
            'en' => '<p>Last updated 5 October 2026.</p><p>Meta is joint controller and independent controller for what it does with it afterwards. We do not sell or otherwise pass data to anyone else.</p>',
        ]);

        $this->migra();

        $contenuto = json_decode((string) DB::table('pages')->where('slug', 'privacy-policy')->value('content'), true);

        $this->assertSame(TestiDelleInformative::contenuto('privacy-policy'), $contenuto);
        $this->assertStringContainsString('href="https://www.facebook.com/legal/controller_addendum"', $contenuto['it']);
        $this->assertStringContainsString('href="https://www.facebook.com/legal/controller_addendum"', $contenuto['en']);
    }

    #[Test]
    public function una_privacy_policy_della_redazione_non_si_tocca(): void
    {
        $this->pubblica(['it' => '<p>Testo della redazione.</p>', 'en' => '<p>Editorial text.</p>']);

        $this->migra();

        $this->assertSame('<p>Testo della redazione.</p>', json_decode((string) DB::table('pages')->where('slug', 'privacy-policy')->value('content'), true)['it']);
    }
}

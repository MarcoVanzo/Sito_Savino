<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La migrazione del 15/10/2026 toglie la web-app `app.savinodelbenevolley.it`
 * (e gli store, che rispondono 404) dalla notizia del modello digitale, sul
 * testo com'è in produzione.
 */
class ViaLaWebAppDallaNotiziaTest extends TestCase
{
    use RefreshDatabase;

    private const PRIMA = "<ul>\n<li><i>la tessera abbonamento</i> può diventare uno strumento</li>\n</ul>\n"
        ."<h2 class=\"western\"><b>Esplora l’universo digitale di Savino Del Bene Volley</b></h2>\n"
        ."<p>L’app “<b>Savino Del Bene Volley</b>” è disponibile per il download sulle piattaforme <b>iOS e Android</b>:<br />\n"
        ."<i><a href=\"https://apps.apple.com/in/app/savino-del-bene-volley/id6470310981\">Clicca qui per scaricare da App Store</a><br />\n"
        ."<a href=\"https://play.google.com/store/apps/details?id=com.savinodelbenevolley.app\">Clicca qui per scaricare da Google Play</a></i></p>\n"
        ."<p>La web-app <b><a href=\"https://app.savinodelbenevolley.it/\">app.savinodelbenevolley.it</a> </b>è accessibile tramite browser e si adatta al dispositivo in uso (pc, tablet, smartphone).</p>\n"
        ."<p>———————————————————–<br />\nQuesto innovativo modello digitale è lanciato oggi.</p>";

    private function migra(): void
    {
        $migrazione = require database_path('migrations/2026_10_15_200000_via_la_web_app_dalla_notizia_del_modello_digitale.php');
        $migrazione->up();
    }

    #[Test]
    public function la_sezione_con_web_app_e_store_sparisce_il_resto_resta(): void
    {
        $notizia = Post::factory()->create(['status' => PostStatus::Published, 'content' => ['it' => self::PRIMA]]);

        $this->migra();
        $testo = (string) $notizia->fresh()->getTranslation('content', 'it');

        $this->assertStringNotContainsString('app.savinodelbenevolley.it', $testo);
        $this->assertStringNotContainsString('apps.apple.com', $testo);
        $this->assertStringNotContainsString('Esplora l’universo digitale', $testo);
        $this->assertStringContainsString("</ul>\n<p>———", $testo);
        $this->assertStringContainsString('Questo innovativo modello digitale è lanciato oggi.', $testo);

        // Rilanciarla non cambia nulla.
        $this->migra();
        $this->assertSame($testo, (string) $notizia->fresh()->getTranslation('content', 'it'));
    }

    #[Test]
    public function con_il_titolo_riscritto_si_toglie_almeno_il_paragrafo_della_web_app(): void
    {
        $notizia = Post::factory()->create(['status' => PostStatus::Published, 'content' => ['it' => str_replace('Esplora l’universo digitale', 'Le nostre app', self::PRIMA)]]);

        $this->migra();
        $testo = (string) $notizia->fresh()->getTranslation('content', 'it');

        $this->assertStringNotContainsString('app.savinodelbenevolley.it', $testo);
        $this->assertStringContainsString('Le nostre app', $testo);
    }
}

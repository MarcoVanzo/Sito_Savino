<?php

namespace Tests\Feature;

use App\Enums\PageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il pannello offriva il template `Public/Shop`, che non aveva il componente
 * Vue: la pagina ricadeva in silenzio su `Public/ContentPage`. I modelli ora
 * stanno tutti in `App\Enums\PageTemplate`, e ognuno deve esistere davvero.
 */
class TemplateDellePagineEsistonoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ogni_modello_ha_il_suo_componente_vue(): void
    {
        foreach (PageTemplate::cases() as $modello) {
            $this->assertFileExists(resource_path("js/Pages/{$modello->componente()}.vue"), $modello->value);
        }
    }

    #[Test]
    public function un_valore_sconosciuto_vale_la_pagina_generica(): void
    {
        $this->assertSame('Public/ContentPage', PageTemplate::componenteDi('Public/Shop'));
        $this->assertSame('Public/ContentPage', PageTemplate::componenteDi('Default'));
        $this->assertSame('Public/ContentPage', PageTemplate::componenteDi(null));
        $this->assertSame('Public/Ticketing', PageTemplate::componenteDi('Public/Ticketing'));
    }

    #[Test]
    public function il_pannello_non_scrive_a_mano_i_nomi_dei_modelli(): void
    {
        $sorgente = file_get_contents(app_path('Filament/Resources/PageResource.php'));

        $this->assertDoesNotMatchRegularExpression("/'Public\\/[A-Za-z\\/]+'/", $sorgente);
        $this->assertArrayNotHasKey('Public/Home', PageTemplate::opzioni());
        $this->assertArrayHasKey('Public/Ticketing', PageTemplate::opzioni());
    }

    #[Test]
    public function la_migrazione_porta_le_pagine_shop_su_content_page(): void
    {
        DB::table('pages')->updateOrInsert(['slug' => 'shop'], [
            'title' => json_encode(['it' => 'Shop', 'en' => 'Shop']),
            'template' => 'Public/Shop',
            'status' => 'publish',
            'updated_at' => now(),
        ]);
        $altra = DB::table('pages')->where('template', '!=', 'Public/Shop')->whereNotNull('template')->first(['id', 'template']);

        (require database_path('migrations/2026_09_27_090000_il_template_shop_non_esiste.php'))->up();

        $this->assertSame('Public/ContentPage', DB::table('pages')->where('slug', 'shop')->value('template'));
        if ($altra !== null) {
            $this->assertSame($altra->template, DB::table('pages')->where('id', $altra->id)->value('template'));
        }
    }
}

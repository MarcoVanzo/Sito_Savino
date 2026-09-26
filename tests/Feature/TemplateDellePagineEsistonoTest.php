<?php

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * Il pannello offriva il template `Public/Shop`, che non aveva né il
 * componente Vue né un posto in `PageController::ALLOWED_TEMPLATES`: la
 * pagina ricadeva in silenzio su `Public/ContentPage`. Ogni template che la
 * redazione può scegliere deve esistere davvero.
 */
class TemplateDellePagineEsistonoTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function consentiti(): array
    {
        return (new ReflectionClassConstant(PageController::class, 'ALLOWED_TEMPLATES'))->getValue();
    }

    #[Test]
    public function ogni_template_consentito_ha_il_suo_componente_vue(): void
    {
        foreach ($this->consentiti() as $template) {
            $this->assertFileExists(resource_path("js/Pages/{$template}.vue"), $template);
        }
    }

    #[Test]
    public function il_pannello_offre_solo_template_che_il_sito_sa_mostrare(): void
    {
        $sorgente = file_get_contents(app_path('Filament/Resources/PageResource.php'));
        preg_match_all("/'(Public\\/[A-Za-z\\/]+)' =>/", $sorgente, $offerti);

        $this->assertNotEmpty($offerti[1]);

        foreach ($offerti[1] as $template) {
            $this->assertContains($template, $this->consentiti(), "Il pannello offre {$template}, che il sito non mostra");
        }
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

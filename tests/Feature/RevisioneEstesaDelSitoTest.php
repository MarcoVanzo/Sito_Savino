<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Game;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La revisione del 20 settembre 2026 estesa a tutto il sito: indirizzi che
 * rispondevano 200 senza esistere, sitemap fatta di rimandi, carrello e
 * checkout in disaccordo sulla spedizione gratuita, dati di prova online.
 */
class RevisioneEstesaDelSitoTest extends TestCase
{
    use RefreshDatabase;

    private function migrazione(): Migration
    {
        return require database_path('migrations/2026_09_20_110000_revisione_dei_dati_e_dell_inglese.php');
    }

    private function pagina(string $slug, string $template, array $attributi = []): Page
    {
        DB::table('pages')->where('slug', $slug)->delete();

        return Page::factory()->create(array_merge([
            'slug' => $slug,
            'template' => $template,
            'status' => PostStatus::Published,
        ], $attributi));
    }

    #[Test]
    public function la_pagina_home_del_cms_rimanda_alla_home(): void
    {
        $this->pagina('home', 'Public/Home');

        $this->get('/home')->assertRedirect('/')->assertStatus(301);
    }

    #[Test]
    public function una_scheda_atleta_inesistente_e_un_404(): void
    {
        $this->get('/stagione/atleta/999-nessuna')->assertNotFound();
    }

    #[Test]
    public function la_sitemap_pubblica_l_indirizzo_di_sezione_e_non_il_rimando(): void
    {
        $this->pagina('organigramma', 'Public/Societa/Organigramma');
        $this->pagina('home', 'Public/Home');
        $this->pagina('privacy-policy', 'Public/ContentPage');
        Cache::flush();

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/societa/organigramma</loc>', $xml);
        $this->assertStringNotContainsString('/organigramma</loc>'."\n", str_replace('/societa/organigramma</loc>', '', $xml));
        $this->assertStringContainsString('/privacy-policy</loc>', $xml);
        $this->assertStringNotContainsString('/home</loc>', $xml);
        $this->assertStringContainsString('/stagione/risultati</loc>', $xml);
    }

    #[Test]
    public function il_carrello_promette_la_soglia_che_il_checkout_applica(): void
    {
        DB::table('shipping_zones')->delete();
        DB::table('site_settings')->where('key', 'shop.free_shipping_threshold')->delete();
        ShippingZone::factory()->create(['countries' => ['IT'], 'free_threshold' => 100, 'is_active' => true]);
        Cache::flush();

        $this->get('/shop/carrello')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->where('freeShippingThreshold', 100));
    }

    #[Test]
    public function la_voce_foto_ufficiale_esce_dal_menu_finche_il_pdf_non_c_e(): void
    {
        DB::table('menu_items')->delete();
        DB::table('site_settings')->where('key', 'official_photo_pdf')->delete();
        $stagione = MenuItem::create(['location' => 'main', 'label' => ['it' => 'Stagione'], 'url' => '/stagione/', 'sort_order' => 0, 'is_active' => true]);
        MenuItem::create(['location' => 'main', 'parent_id' => $stagione->id, 'label' => ['it' => 'Foto Ufficiale'], 'url' => '/stagione/foto-ufficiale/', 'sort_order' => 0, 'is_active' => true]);
        MenuItem::create(['location' => 'main', 'parent_id' => $stagione->id, 'label' => ['it' => 'Risultati'], 'url' => '/stagione/risultati/', 'sort_order' => 1, 'is_active' => true]);
        SiteSetting::clearCache();
        MenuItem::clearCache();

        $this->assertSame(['Risultati'], array_column(MenuItem::getTree('main')[0]['children'], 'label'));

        // Salvare l'impostazione svuota anche la cache del menu.
        SiteSetting::set('official_photo_pdf', 'official-photos/foto.pdf');

        $this->assertSame(['Foto Ufficiale', 'Risultati'], array_column(MenuItem::getTree('main')[0]['children'], 'label'));
    }

    #[Test]
    public function la_migrazione_toglie_i_dati_di_prova_e_sistema_le_impostazioni(): void
    {
        Bus::fake();

        DB::table('site_settings')->whereIn('key', ['hero_video_url', 'stats.0.value', 'stats', 'footer_copyright'])->delete();
        DB::table('site_settings')->insert([
            ['key' => 'hero_video_url', 'value' => '', 'type' => 'text', 'group' => 'general'],
            ['key' => 'stats.0.value', 'value' => '40+', 'type' => 'text', 'group' => 'general'],
            ['key' => 'stats', 'value' => json_encode(['en' => [['value' => 'Serie A1  ', 'label' => 'League']]]), 'type' => 'json', 'group' => 'home'],
            ['key' => 'footer_copyright', 'value' => '© {year} Savino Del Bene Volley — Tutti i diritti riservati.', 'type' => 'text', 'group' => 'footer'],
        ]);

        $tag = DB::table('tags')->insertGetId(['name' => 'test', 'slug' => 'test']);
        $vero = DB::table('tags')->insertGetId(['name' => 'Champions League', 'slug' => 'champions-league']);

        $this->migrazione()->up();
        $this->migrazione()->up();

        $this->assertSame('home', DB::table('site_settings')->where('key', 'hero_video_url')->value('group'));
        $this->assertDatabaseMissing('site_settings', ['key' => 'stats.0.value']);
        $this->assertSame('Serie A1', json_decode((string) DB::table('site_settings')->where('key', 'stats')->value('value'), true)['en'][0]['value']);
        $this->assertSame('', DB::table('site_settings')->where('key', 'footer_copyright')->value('value'));
        $this->assertDatabaseMissing('tags', ['id' => $tag]);
        $this->assertDatabaseHas('tags', ['id' => $vero]);
    }

    #[Test]
    public function la_migrazione_toglie_solo_le_avversarie_rimaste_senza_niente(): void
    {
        Bus::fake();

        $interna = Team::factory()->create(['name' => 'Savino Under 13', 'is_internal' => true])->id;
        $orfana = Team::factory()->create(['name' => 'Club Italia', 'is_internal' => false])->id;
        $avversaria = Team::factory()->create(['name' => 'Chieri', 'is_internal' => false])->id;
        // Un'avversaria vera ha almeno una gara in calendario.
        Game::factory()->create(['home_team_id' => $interna, 'away_team_id' => $avversaria]);

        $this->migrazione()->up();

        $this->assertDatabaseHas('teams', ['id' => $interna]);
        $this->assertDatabaseHas('teams', ['id' => $avversaria]);
        $this->assertDatabaseMissing('teams', ['id' => $orfana]);
    }

    #[Test]
    public function la_storia_in_inglese_non_racconta_piu_un_club_del_1982(): void
    {
        Bus::fake();

        $pagina = $this->pagina('storia', 'Public/Societa/Storia', [
            'content' => [
                'it' => '<h2>Le Origini</h2><p>La Crescita</p><p>Con la partnership strategica…</p>',
                'en' => '<h2>The Beginnings</h2><p>Founded in Scandicci in 1982, Savino Del Bene Volley…</p>',
            ],
            'content_data' => [
                'it' => ['timeline' => [['year' => '2012', 'title' => 'Le Origini', 'description' => 'Testo']]],
                'en' => ['timeline' => [['year' => '1982', 'title' => 'The Origins', 'description' => 'Text']]],
            ],
        ]);

        $this->migrazione()->up();

        $testo = json_decode((string) DB::table('pages')->where('id', $pagina->id)->value('content'), true);
        $dati = json_decode((string) DB::table('pages')->where('id', $pagina->id)->value('content_data'), true);

        $this->assertStringStartsWith('<h2>La Crescita</h2><p>Con la partnership', $testo['it']);
        $this->assertStringNotContainsString('1982', $testo['en']);
        $this->assertSame('2012', $dati['en']['timeline'][0]['year']);
        $this->assertCount(11, $dati['en']['timeline']);
        $this->assertTrue(array_is_list($dati['en']['timeline']));
    }
}

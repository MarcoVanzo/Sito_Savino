<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La revisione di settembre della redazione: le modifiche fatte dal pannello
 * non arrivavano al sito o facevano sparire quello che c'era.
 *
 * Qui le migrazioni di riparazione e le rotte che riportano le sezioni sulla
 * pagina che la redazione modifica davvero.
 */
class RevisioneSettembrePagineTest extends TestCase
{
    use RefreshDatabase;

    private function migrazione(string $nome): Migration
    {
        return require database_path("migrations/{$nome}.php");
    }

    #[Test]
    public function gli_elenchi_salvati_come_mappe_uuid_tornano_elenchi(): void
    {
        $pagina = Page::factory()->create([
            'slug' => 'abbonamenti',
            'template' => 'Public/Ticketing',
            'status' => PostStatus::Published,
            'content_data' => [
                'it' => [
                    'hero_label' => 'Believe',
                    'plans' => [
                        '3678fee3-1850-4b49-b2d2-2d1743568de8' => ['name' => 'Tribuna Ovest', 'price' => '460', 'features' => []],
                        '90665992-591d-4c90-a6fc-07461799f35d' => ['name' => 'Tribuna Nord', 'price' => '310', 'features' => []],
                    ],
                    'documents' => [
                        '8f645f06-aae9-451a-b075-0b15227d2ae6' => [
                            'title' => 'Bilancio',
                            'file' => ['10a26640-851e-4a86-b195-34990b8802bd' => 'documenti/bilancio.pdf'],
                        ],
                    ],
                ],
                'en' => ['plans' => [['name' => 'West Stand', 'price' => '460']]],
            ],
        ]);

        $this->migrazione('2026_09_08_100000_riporta_a_elenco_i_repeater_salvati_come_mappe')->up();

        $italiano = $pagina->refresh()->getTranslation('content_data', 'it');
        $inglese = $pagina->getTranslation('content_data', 'en');

        $this->assertTrue(array_is_list($italiano['plans']));
        $this->assertSame(['Tribuna Ovest', 'Tribuna Nord'], array_column($italiano['plans'], 'name'));
        $this->assertSame('documenti/bilancio.pdf', $italiano['documents'][0]['file']);
        $this->assertSame('Believe', $italiano['hero_label']);
        $this->assertSame([['name' => 'West Stand', 'price' => '460']], $inglese['plans']);
    }

    #[Test]
    public function la_sezione_ticketing_porta_alla_biglietteria(): void
    {
        Page::factory()->create(['slug' => 'biglietteria', 'template' => 'Public/Ticketing', 'status' => PostStatus::Published]);

        $this->get('/ticketing')->assertRedirect('/ticketing/biglietteria')->assertStatus(301);
        $this->get('/ticketing/biglietteria')->assertOk();
    }

    #[Test]
    public function la_sezione_youth_porta_al_settore_giovanile(): void
    {
        Page::factory()->create(['slug' => 'settore-giovanile', 'template' => 'Public/Youth', 'status' => PostStatus::Published]);

        $this->get('/youth')->assertRedirect('/youth/settore-giovanile')->assertStatus(301);
        $this->get('/youth/settore-giovanile')->assertOk();
    }

    /**
     * La copia del seeder se ne va; una pagina con lo stesso slug ma
     * riscritta dalla redazione resta.
     */
    #[Test]
    public function le_copie_del_seeder_delle_sezioni_vengono_tolte(): void
    {
        $copia = Page::factory()->create([
            'slug' => 'youth',
            'template' => 'Public/Youth',
            'content_data' => ['it' => ['intro_title' => 'Formare Campioni Dentro e Fuori dal Campo']],
        ]);
        $riscritta = Page::factory()->create([
            'slug' => 'ticketing',
            'template' => 'Public/Ticketing',
            'content_data' => ['it' => ['plans' => [['name' => 'Tribuna Ovest', 'price' => '460']]]],
        ]);
        $vera = Page::factory()->create(['slug' => 'settore-giovanile', 'template' => 'Public/Youth']);

        $ticketing = MenuItem::create(['label' => ['it' => 'Ticketing'], 'url' => '/ticketing/', 'location' => 'main', 'sort_order' => 2]);
        $biglietteria = MenuItem::create(['label' => ['it' => 'Biglietteria'], 'url' => '/ticketing/', 'location' => 'main', 'parent_id' => $ticketing->id, 'sort_order' => 0]);

        $this->migrazione('2026_09_08_130000_toglie_le_copie_seed_delle_pagine_di_sezione')->up();

        $this->assertDatabaseMissing('pages', ['id' => $copia->id]);
        $this->assertDatabaseHas('pages', ['id' => $riscritta->id]);
        $this->assertDatabaseHas('pages', ['id' => $vera->id]);
        $this->assertSame('/ticketing/', $ticketing->refresh()->url);
        $this->assertSame('/ticketing/biglietteria/', $biglietteria->refresh()->url);
    }

    #[Test]
    public function double_face_e_un_podcast_anche_nel_menu(): void
    {
        $voce = MenuItem::create([
            'label' => ['it' => 'Double Face', 'en' => 'Double Face'],
            'url' => '/comunicazione/double-face',
            'description' => ['it' => 'Il magazine ufficiale', 'en' => 'The official magazine'],
            'location' => 'main',
        ]);
        $pagina = Page::factory()->create([
            'slug' => 'double-face',
            'title' => ['it' => 'Double Face — Il Podcast', 'en' => 'Double Face — The Magazine'],
            'content' => ['it' => '<p>Il podcast ufficiale.</p>', 'en' => '<p>The official magazine of Savino Del Bene Volley.</p>'],
        ]);

        $this->migrazione('2026_09_08_110000_double_face_e_un_podcast')->up();

        $this->assertSame('Il podcast ufficiale', $voce->refresh()->getTranslation('description', 'it'));
        $this->assertSame('The official podcast', $voce->getTranslation('description', 'en'));
        $this->assertSame('Double Face — The Podcast', $pagina->refresh()->getTranslation('title', 'en'));
        $this->assertSame('<p>The official podcast of Savino Del Bene Volley.</p>', $pagina->getTranslation('content', 'en'));
        $this->assertSame('Double Face — Il Podcast', $pagina->getTranslation('title', 'it'));
    }

    #[Test]
    public function affiliazioni_sostituisce_il_segnaposto_ma_non_un_testo_della_redazione(): void
    {
        $segnaposto = Page::factory()->create([
            'slug' => 'affiliazioni',
            'content' => ['it' => '<h2>Programma Affiliazioni</h2><p>La Savino Del Bene Volley offre un programma di affiliazione per società sportive e scuole di pallavolo del territorio.</p>'],
        ]);

        $this->migrazione('2026_09_08_120000_affiliazioni_con_i_testi_del_vecchio_sito')->up();

        $this->assertStringContainsString('Un progetto per crescere insieme', $segnaposto->refresh()->getTranslation('content', 'it'));
        $this->assertStringContainsString('A project to grow together', $segnaposto->getTranslation('content', 'en'));

        $segnaposto->setTranslation('content', 'it', '<p>Testo scritto dalla redazione.</p>')->save();

        $this->migrazione('2026_09_08_120000_affiliazioni_con_i_testi_del_vecchio_sito')->up();

        $this->assertSame('<p>Testo scritto dalla redazione.</p>', $segnaposto->refresh()->getTranslation('content', 'it'));
    }

    #[Test]
    public function la_pagina_club_race_nasce_in_bozza_sotto_il_ticketing(): void
    {
        // Il database di prova ha gia' tutte le migrazioni applicate: si
        // riparte da zero per vedere cosa fa questa da sola.
        DB::table('menu_items')->where('url', 'like', '%/club-race%')->delete();
        DB::table('pages')->where('slug', 'club-race')->delete();

        // Il menu principale puo' esserci gia' (lo creano le migrazioni).
        $ticketing = MenuItem::query()->whereNull('parent_id')->where('location', 'main')->where('url', 'like', '/ticketing%')->first()
            ?? MenuItem::create(['label' => ['it' => 'Ticketing'], 'url' => '/ticketing/', 'location' => 'main', 'sort_order' => 2]);
        MenuItem::create(['label' => ['it' => 'Abbonamenti'], 'url' => '/ticketing/abbonamenti/', 'location' => 'main', 'parent_id' => $ticketing->id, 'sort_order' => 1]);
        $ultimaPosizione = (int) DB::table('menu_items')->where('parent_id', $ticketing->id)->max('sort_order');

        $this->migrazione('2026_09_08_140000_crea_la_pagina_club_race')->up();
        // Rilanciata, non duplica.
        $this->migrazione('2026_09_08_140000_crea_la_pagina_club_race')->up();

        $this->assertSame(1, DB::table('pages')->where('slug', 'club-race')->count());
        $this->assertDatabaseHas('pages', ['slug' => 'club-race', 'template' => 'Public/ClubRace', 'status' => 'draft']);

        $voci = DB::table('menu_items')->where('url', '/ticketing/club-race/')->get();
        $this->assertCount(1, $voci);
        $this->assertSame($ticketing->id, (int) $voci[0]->parent_id);
        $this->assertSame($ultimaPosizione + 1, (int) $voci[0]->sort_order);
    }

    #[Test]
    public function la_pagina_club_race_pubblicata_usa_il_suo_template(): void
    {
        // La migrazione l'ha gia' creata in bozza: qui si pubblica.
        DB::table('pages')->where('slug', 'club-race')->delete();
        Page::factory()->create([
            'slug' => 'club-race',
            'template' => 'Public/ClubRace',
            'status' => PostStatus::Published,
            'content_data' => ['it' => ['standings' => [['club' => 'Fusion Team Volley', 'points' => 30]]]],
        ]);

        $this->get('/ticketing/club-race')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Public/ClubRace')
                ->where('page.content_data.standings.0.club', 'Fusion Team Volley'));

        // Dalla rotta generica si viene riportati alla sezione.
        $this->get('/club-race')->assertRedirect('/ticketing/club-race');
    }
}

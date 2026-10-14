<?php

namespace Tests\Feature;

use App\Enums\HonourMedal;
use App\Enums\PlayerHonourCategory;
use App\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\PlayerHonour;
use App\Models\Roster;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PalmaresPageTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();

        $season = Season::factory()->create(['is_current' => true]);
        $team = Team::factory()->create([
            'slug' => 'savino-del-bene-volley',
            'category' => 'A1',
            'is_internal' => true,
        ]);

        $this->player = Player::factory()->create([
            'first_name' => 'Maja',
            'last_name' => 'Ognjenovic',
            'wikipedia_title' => 'Maja Ognjenović',
            'wikipedia_lang' => 'it',
        ]);

        Roster::factory()->create([
            'player_id' => $this->player->id,
            'team_id' => $team->id,
            'season_id' => $season->id,
            'jersey_number' => 10,
            'role' => PlayerPosition::Setter,
        ]);
    }

    private function honour(array $attributes = []): PlayerHonour
    {
        return $this->player->honours()->create(array_merge([
            'category' => PlayerHonourCategory::Club,
            'competition' => ['it' => 'Coppa CEV', 'en' => 'CEV Cup'],
            'edition' => '2017-18',
            'year' => 2017,
            'source' => PlayerHonour::SOURCE_WIKIPEDIA,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function palmares(): ?array
    {
        // Il palmarès sta nella pagina dell'atleta, non più nella rosa.
        return $this->get('/stagione/atleta/'.$this->player->id.'-maja-ognjenovic')
            ->assertOk()
            ->viewData('page')['props']['palmares'];
    }

    #[Test]
    public function la_pagina_rosa_pubblica_il_palmares_raggruppato(): void
    {
        $this->honour();
        $this->honour(['edition' => '2021-22', 'year' => 2021]);
        $this->honour([
            'category' => PlayerHonourCategory::National,
            'competition' => ['it' => 'Campionato mondiale'],
            'edition' => 'Giappone 2018',
            'year' => 2018,
            'medal' => HonourMedal::Gold,
        ]);

        $palmares = $this->palmares();

        $this->assertSame(3, $palmares['total']);
        $this->assertSame(['club' => 2, 'national' => 1], $palmares['totals']);

        // Due edizioni della stessa coppa sono una riga sola con il conteggio.
        $club = $palmares['groups'][0];
        $this->assertSame('club', $club['category']);
        $this->assertCount(1, $club['items']);
        $this->assertSame(2, $club['items'][0]['count']);
        $this->assertSame(['2021-22', '2017-18'], $club['items'][0]['editions']);
    }

    #[Test]
    public function le_righe_nascoste_non_arrivano_al_sito(): void
    {
        $this->honour();
        $this->honour(['edition' => '2021-22', 'year' => 2021, 'is_visible' => false]);

        $palmares = $this->palmares();

        $this->assertSame(1, $palmares['total']);
        $this->assertSame(['2017-18'], $palmares['groups'][0]['items'][0]['editions']);
    }

    #[Test]
    public function unatleta_senza_titoli_non_riceve_un_palmares_vuoto(): void
    {
        $this->assertNull($this->palmares());
    }

    #[Test]
    public function la_fonte_e_citata_con_il_link_alla_voce(): void
    {
        $this->honour();

        $source = $this->palmares()['source'];

        $this->assertSame('Wikipedia', $source['name']);
        $this->assertSame('https://it.wikipedia.org/wiki/Maja_Ognjenovi%C4%87', $source['url']);
    }

    #[Test]
    public function la_pagina_dellatleta_ha_scheda_e_carriera(): void
    {
        $slug = $this->player->id.'-maja-ognjenovic';

        $page = $this->get("/stagione/atleta/{$slug}")->assertOk()->viewData('page');

        $this->assertSame('Public/Atleta', $page['component']);
        $this->assertSame('Maja', $page['props']['atleta']['first_name']);
        $this->assertSame(10, $page['props']['atleta']['jersey_number']);
        $this->assertCount(1, $page['props']['carriera']);
    }

    #[Test]
    public function la_rosa_della_prima_squadra_porta_alle_schede_senza_il_palmares(): void
    {
        $props = $this->get('/stagione')->assertOk()->viewData('page')['props'];

        $this->assertTrue($props['schedeAtlete']);
        $this->assertSame($this->player->id.'-maja-ognjenovic', $props['roster'][0]['playerSlug']);
        $this->assertArrayNotHasKey('palmares', $props['roster'][0]);
    }

    #[Test]
    public function le_giovanili_non_hanno_schede_atleta(): void
    {
        $props = $this->get('/stagione/b1')->assertOk()->viewData('page')['props'];

        $this->assertFalse($props['schedeAtlete']);
    }
}

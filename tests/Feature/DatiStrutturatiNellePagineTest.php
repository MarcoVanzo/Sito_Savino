<?php

namespace Tests\Feature;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Enums\PlayerPosition;
use App\Enums\PostStatus;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Roster;
use App\Models\Season;
use App\Models\SiteSetting;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * I dati strutturati (JSON-LD) stanno nell'HTML servito, non li scrive il
 * JavaScript: per un campione di pagine si estraggono i blocchi
 * `application/ld+json` dall'HTML e si controlla che siano JSON validi con i
 * campi che Google chiede per ogni tipo (audit SEO del 14/10/2026).
 */
class DatiStrutturatiNellePagineTest extends TestCase
{
    use RefreshDatabase;

    private Team $savino;

    private Team $avversaria;

    private Season $stagione;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();

        $this->stagione = Season::factory()->create(['is_current' => true]);
        $this->savino = Team::factory()->create([
            'name' => 'Savino Del Bene Scandicci',
            'slug' => 'savino-del-bene-volley',
            'category' => 'A1',
            'is_internal' => true,
        ]);
        $this->avversaria = Team::factory()->create(['name' => 'Igor Gorgonzola Novara', 'is_internal' => false]);
    }

    /**
     * I blocchi JSON-LD dell'HTML, decodificati: un blocco non valido fa
     * fallire il test.
     *
     * @return list<array<string, mixed>>
     */
    private function blocchi(string $indirizzo): array
    {
        $html = $this->get($indirizzo)->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', (string) $html, $trovati);

        return array_map(function (string $json): array {
            $blocco = json_decode($json, true);
            $this->assertIsArray($blocco, 'JSON-LD non valido: '.$json);
            $this->assertSame('https://schema.org', $blocco['@context']);

            return $blocco;
        }, $trovati[1]);
    }

    /**
     * @param  list<array<string, mixed>>  $blocchi
     * @return array<string, mixed>
     */
    private function delTipo(array $blocchi, string $tipo): array
    {
        $trovati = array_values(array_filter($blocchi, fn (array $blocco) => $blocco['@type'] === $tipo));
        $this->assertNotEmpty($trovati, "Nessun blocco {$tipo}");

        return $trovati[0];
    }

    /**
     * @param  array<string, mixed>  $blocco
     * @param  list<string>  $campi
     */
    private function haICampi(array $blocco, array $campi): void
    {
        foreach ($campi as $campo) {
            $this->assertArrayHasKey($campo, $blocco, "{$blocco['@type']} senza `{$campo}`");
            $this->assertNotEmpty($blocco[$campo], "{$blocco['@type']} con `{$campo}` vuoto");
        }
    }

    private function garaInCasa(array $attributi = []): Game
    {
        return Game::factory()->create(array_merge([
            'season_id' => $this->stagione->id,
            'home_team_id' => $this->savino->id,
            'away_team_id' => $this->avversaria->id,
            'match_date' => now()->addDays(3)->setTime(20, 0),
            'status' => GameStatus::Scheduled,
            'competition_type' => CompetitionType::Championship,
            'location' => 'Pala BigMat - Firenze',
        ], $attributi));
    }

    #[Test]
    public function la_home_ha_societa_sito_e_prossima_partita_con_i_biglietti(): void
    {
        SiteSetting::set('match_day_popup_url', 'https://www.vivaticket.com/it/ticket/savino-novara/1');
        SiteSetting::set('social_facebook', 'https://www.facebook.com/savinodelbenevolley');
        $this->garaInCasa();

        $blocchi = $this->blocchi('/');

        $squadra = $this->delTipo($blocchi, 'SportsTeam');
        $this->haICampi($squadra, ['name', 'url', 'logo', 'sameAs']);
        $this->assertContains('https://it.wikipedia.org/wiki/Pallavolo_Scandicci_Savino_Del_Bene', $squadra['sameAs']);
        $this->assertContains('https://www.facebook.com/savinodelbenevolley', $squadra['sameAs']);
        $this->assertNotEmpty(array_filter($squadra['sameAs'], fn ($url) => str_contains($url, 'legavolleyfemminile.it/club/')));

        $this->haICampi($this->delTipo($blocchi, 'WebSite'), ['name', 'url']);

        $gara = $this->delTipo($blocchi, 'SportsEvent');
        $this->haICampi($gara, ['name', 'startDate', 'location', 'homeTeam', 'awayTeam', 'eventStatus', 'organizer', 'image', 'offers']);
        // Data con il fuso, luogo con l'indirizzo.
        $this->assertMatchesRegularExpression('/T20:00:00\+0[12]:00$/', $gara['startDate']);
        $this->assertSame('Pala BigMat', $gara['location']['name']);
        $this->assertSame('Firenze', $gara['location']['address']['addressLocality']);
        $this->assertSame('https://www.vivaticket.com/it/ticket/savino-novara/1', $gara['offers']['url']);
    }

    #[Test]
    public function il_calendario_cerca_la_prossima_gara_in_casa_una_volta_sola(): void
    {
        foreach (range(1, 4) as $settimana) {
            $this->garaInCasa(['match_date' => now()->addWeeks($settimana)->setTime(20, 0)]);
        }

        DB::enableQueryLog();
        $this->get('/stagione/risultati')->assertOk();

        $ricerche = array_filter(
            DB::getQueryLog(),
            fn (array $query) => str_starts_with($query['query'], 'select `id` from `games`'),
        );
        $this->assertCount(1, $ricerche);
    }

    #[Test]
    public function societa_e_sito_stanno_solo_in_home(): void
    {
        $tipi = array_column($this->blocchi('/stagione'), '@type');

        $this->assertNotContains('SportsTeam', $tipi);
        $this->assertNotContains('WebSite', $tipi);
        $this->assertContains('BreadcrumbList', $tipi);
    }

    #[Test]
    public function senza_link_di_vendita_la_partita_non_ha_offerte(): void
    {
        $this->garaInCasa();

        $gara = $this->delTipo($this->blocchi('/'), 'SportsEvent');

        $this->assertArrayNotHasKey('offers', $gara);
    }

    #[Test]
    public function la_notizia_e_un_newsarticle(): void
    {
        $post = Post::factory()->create([
            'title' => str_repeat('Una vittoria lunghissima da raccontare ', 4),
            'slug' => 'vittoria-a-novara',
            // Le notizie importate non hanno autore: firma la società.
            'author_id' => null,
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $blocchi = $this->blocchi('/news/'.$post->slug);

        $articolo = $this->delTipo($blocchi, 'NewsArticle');
        $this->haICampi($articolo, ['headline', 'datePublished', 'dateModified', 'author', 'publisher', 'image', 'mainEntityOfPage']);
        $this->assertLessThanOrEqual(110, mb_strlen($articolo['headline']));
        $this->assertSame('Organization', $articolo['author']['@type']);
        $this->assertNotEmpty($articolo['publisher']['logo']['url']);

        $briciole = $this->delTipo($blocchi, 'BreadcrumbList');
        $this->assertSame(['Savino Del Bene Volley', 'News'], array_column(array_slice($briciole['itemListElement'], 0, 2), 'name'));
    }

    #[Test]
    public function la_partita_giocata_e_uno_sportsevent(): void
    {
        $gara = $this->garaInCasa([
            'status' => GameStatus::Completed,
            'home_score' => 3,
            'away_score' => 1,
            'match_date' => now()->subDays(2),
        ]);
        GamePlayerStat::create([
            'game_id' => $gara->id,
            'team_id' => $this->savino->id,
            'player_name' => 'Rossi Maria',
            'jersey_number' => 7,
            'sets_played' => 4,
            'points_total' => 15,
        ]);

        $evento = $this->delTipo($this->blocchi('/stagione/risultati/'.$gara->id), 'SportsEvent');

        $this->haICampi($evento, ['name', 'startDate', 'location', 'homeTeam', 'awayTeam', 'eventStatus', 'organizer', 'image']);
        $this->assertSame('Savino Del Bene Scandicci', $evento['homeTeam']['name']);
        // Un evento annidato Google lo legge come un secondo evento non valido.
        $this->assertArrayNotHasKey('superEvent', $evento);
        $this->assertStringContainsString('3-1', $evento['description']);
        $this->assertCount(2, $evento['performer']);
        $this->assertStringEndsWith('/stagione/risultati/'.$gara->id, $evento['url']);
    }

    #[Test]
    public function il_calendario_elenca_le_gare_della_societa(): void
    {
        $this->garaInCasa();
        // Una gara fra due avversarie: è in calendario ma non è nostra.
        Game::factory()->create([
            'season_id' => $this->stagione->id,
            'competition_type' => CompetitionType::Championship,
        ]);

        $eventi = array_filter($this->blocchi('/stagione/risultati'), fn ($blocco) => $blocco['@type'] === 'SportsEvent');

        $this->assertCount(1, $eventi);
    }

    #[Test]
    public function il_prodotto_ha_prezzo_e_disponibilita(): void
    {
        $prodotto = Product::factory()->create([
            'name' => 'Maglia gara 2026',
            'slug' => 'maglia-gara-2026',
            'sku' => 'SDB-MAGLIA-26',
            'price' => 70,
            'stock' => 5,
            'is_active' => true,
        ]);

        $scheda = $this->delTipo($this->blocchi('/shop/prodotto/'.$prodotto->slug), 'Product');

        $this->haICampi($scheda, ['name', 'image', 'sku', 'brand', 'offers', 'url']);
        $this->assertSame('70.00', $scheda['offers']['price']);
        $this->assertSame('EUR', $scheda['offers']['priceCurrency']);
        $this->assertStringStartsWith('https://schema.org/', $scheda['offers']['availability']);
        $this->assertArrayNotHasKey('aggregateRating', $scheda);
        $this->assertArrayNotHasKey('review', $scheda);
    }

    #[Test]
    public function la_categoria_dello_shop_e_un_itemlist(): void
    {
        $categoria = ProductCategory::factory()->create(['name' => 'Kit Gara', 'slug' => 'kit-gara-di-prova']);
        Product::factory()->count(2)->create(['product_category_id' => $categoria->id, 'is_active' => true]);

        $blocchi = $this->blocchi('/shop/categoria/kit-gara-di-prova');

        $elenco = $this->delTipo($blocchi, 'ItemList');
        $this->assertSame(2, $elenco['numberOfItems']);
        $this->haICampi($elenco['itemListElement'][0], ['position', 'url', 'name']);
        $this->haICampi($this->delTipo($blocchi, 'BreadcrumbList'), ['itemListElement']);
    }

    #[Test]
    public function la_pagina_squadra_ha_le_briciole(): void
    {
        $briciole = $this->delTipo($this->blocchi('/stagione/u17'), 'BreadcrumbList');

        $this->assertSame(1, $briciole['itemListElement'][0]['position']);
        $this->assertNotEmpty($briciole['itemListElement'][0]['item']);
        $this->assertArrayNotHasKey('item', end($briciole['itemListElement']));
    }

    #[Test]
    public function l_atleta_e_una_person_della_squadra(): void
    {
        $atleta = Player::factory()->create(['first_name' => 'Ekaterina', 'last_name' => 'Antropova', 'nationality' => 'Italia']);
        Roster::factory()->create([
            'player_id' => $atleta->id,
            'team_id' => $this->savino->id,
            'season_id' => $this->stagione->id,
            'role' => PlayerPosition::Opposite,
            'height_cm' => 202,
        ]);

        $persona = $this->delTipo($this->blocchi('/stagione/atleta/'.$atleta->id.'-ekaterina-antropova'), 'Person');

        $this->haICampi($persona, ['name', 'url', 'jobTitle', 'memberOf', 'nationality', 'height']);
        $this->assertSame('Savino Del Bene Volley', $persona['memberOf']['name']);
        $this->assertArrayNotHasKey('birthDate', $persona);
    }

    #[Test]
    public function un_titolo_con_il_tag_di_chiusura_non_rompe_il_blocco(): void
    {
        $post = Post::factory()->create([
            'title' => 'Prova </script><script>alert(1)</script>',
            'slug' => 'prova-script',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        $html = (string) $this->get('/news/'.$post->slug)->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertSame('NewsArticle', $this->delTipo($this->blocchi('/news/'.$post->slug), 'NewsArticle')['@type']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\CompetitionType;
use App\Enums\GameStatus;
use App\Enums\PlayerPosition;
use App\Enums\PostStatus;
use App\Enums\ProductType;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Page;
use App\Models\Player;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Roster;
use App\Models\Season;
use App\Models\ShippingZone;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Services\Seo\DatiStrutturati;
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
            'biglietti_prezzo_da' => 10,
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
        $this->assertMatchesRegularExpression('/T23:59:59\+0[12]:00$/', $gara['endDate']);
        $this->assertSame(substr($gara['startDate'], 0, 10), substr($gara['endDate'], 0, 10));
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
    public function la_gara_in_casa_con_i_biglietti_della_scheda_ha_l_offerta_completa(): void
    {
        $this->paginaDelPalazzetto();
        // Non è la prossima in casa: l'offerta viene dai dati della scheda.
        $this->garaInCasa(['match_date' => now()->addDay()->setTime(18, 0)]);
        $gara = $this->garaInCasa([
            'match_date' => now()->addDays(10)->setTime(20, 30),
            'location' => 'Palazzo Wanny FIRENZE',
            'biglietti_url' => 'https://www.vivaticket.com/it/ticket/savino-conegliano/2',
            'biglietti_in_vendita_dal' => now()->subDays(2)->setTime(10, 0),
            'biglietti_prezzo_da' => 12.5,
            'biglietti_esauriti' => true,
        ]);

        $evento = collect($this->blocchi('/stagione/risultati'))
            ->first(fn (array $blocco) => $blocco['@type'] === 'SportsEvent'
                && ($blocco['offers']['url'] ?? null) === $gara->biglietti_url);

        $this->assertNotNull($evento, 'Nessun SportsEvent con l\'offerta della scheda');
        $this->assertSame('Offer', $evento['offers']['@type']);
        $this->assertSame('https://schema.org/SoldOut', $evento['offers']['availability']);
        $this->assertSame('12.50', $evento['offers']['price']);
        $this->assertSame('EUR', $evento['offers']['priceCurrency']);
        $this->assertMatchesRegularExpression('/T10:00:00\+0[12]:00$/', $evento['offers']['validFrom']);
        $this->assertMatchesRegularExpression('/T20:30:00\+0[12]:00$/', $evento['startDate']);
        // La CEV scrive il nome di prima: è comunque il palazzetto, con l'indirizzo intero.
        $this->assertSame('Pala BigMat', $evento['location']['name']);
        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => 'Via del Cavallaccio, 18/20/22/24',
            'postalCode' => '50142',
            'addressLocality' => 'Firenze',
            'addressRegion' => 'FI',
            'addressCountry' => 'IT',
        ], $evento['location']['address']);
    }

    #[Test]
    public function senza_prezzo_la_partita_non_dichiara_l_offerta(): void
    {
        // Un'offerta con il prezzo vuoto e' quella che il Rich Results Test
        // segnalava (audit SEO del 20/10/2026): meglio nessuna.
        $this->garaInCasa([
            'biglietti_url' => 'https://www.vivaticket.com/it/ticket/savino-novara/1',
            'biglietti_prezzo_da' => null,
        ]);

        $evento = $this->delTipo($this->blocchi('/'), 'SportsEvent');

        $this->assertArrayNotHasKey('offers', $evento);
    }

    #[Test]
    public function la_prossima_in_casa_senza_link_porta_alla_biglietteria(): void
    {
        Page::updateOrCreate(['slug' => 'biglietteria'], [
            'title' => ['it' => 'Biglietteria', 'en' => 'Tickets'],
            'template' => 'Public/Ticketing',
            'status' => PostStatus::Published,
            'content_data' => ['it' => ['tickets_url' => null]],
        ]);
        $this->garaInCasa();

        $offerta = $this->delTipo($this->blocchi('/'), 'SportsEvent')['offers'];

        $this->assertSame(url('/ticketing/biglietteria'), $offerta['url']);
    }

    #[Test]
    public function prima_dell_inizio_vendita_i_biglietti_sono_in_prevendita(): void
    {
        $this->garaInCasa([
            'biglietti_url' => 'https://www.vivaticket.com/it/ticket/savino-novara/1',
            'biglietti_in_vendita_dal' => now()->addDay()->setTime(10, 0),
        ]);

        $offerta = $this->delTipo($this->blocchi('/'), 'SportsEvent')['offers'];

        $this->assertSame('https://schema.org/PreOrder', $offerta['availability']);
        $this->assertArrayHasKey('validFrom', $offerta);
    }

    #[Test]
    public function una_gara_che_non_e_la_prossima_senza_link_non_dichiara_biglietti(): void
    {
        SiteSetting::set('match_day_popup_url', 'https://www.vivaticket.com/it/ticket/savino-novara/1');
        $this->garaInCasa(['match_date' => now()->addDay()->setTime(18, 0)]);
        $dopo = $this->garaInCasa([
            'match_date' => now()->addDays(10)->setTime(20, 30),
            'biglietti_in_vendita_dal' => now()->subDay(),
            'biglietti_prezzo_da' => 12,
        ]);

        $evento = app(DatiStrutturati::class)->gara($dopo, 'it');

        $this->assertArrayNotHasKey('offers', $evento);
    }

    #[Test]
    public function le_trasferte_cev_hanno_il_paese_solo_se_si_sa(): void
    {
        $this->garaInCasa(['location' => 'PalaIgor - Novara']);
        $trasferta = fn (string $luogo): array => app(DatiStrutturati::class)->gara(Game::factory()->create([
            'season_id' => $this->stagione->id,
            'home_team_id' => $this->avversaria->id,
            'away_team_id' => $this->savino->id,
            'cev_match_id' => random_int(1, 999999),
            'location' => $luogo,
        ]), 'it')['location'];

        $istanbul = $trasferta('Eczacibasi Spor Salonu ISTANBUL');
        $this->assertSame('Eczacibasi Spor Salonu ISTANBUL', $istanbul['name']);
        $this->assertSame(['@type' => 'PostalAddress', 'addressLocality' => 'Istanbul', 'addressCountry' => 'TR'], $istanbul['address']);

        // Città ignota: niente paese, e la sigla non fa parte della città.
        $this->assertSame(['@type' => 'PostalAddress', 'addressLocality' => 'Obrenovac'], $trasferta('Sportsko Kulturni Centar - SKC OBRENOVAC')['address']);

        // Una città dove gioca la Lega è in Italia.
        $this->assertSame('IT', $trasferta('Pala Igor Gorgonzola NOVARA')['address']['addressCountry']);
        $this->assertSame('Tovuz', $trasferta('TOVUZ Olympic Hall')['address']['addressLocality']);
    }

    #[Test]
    public function il_derby_fuori_casa_al_palazzetto_ha_l_indirizzo_intero(): void
    {
        $this->paginaDelPalazzetto();
        $derby = Game::factory()->create([
            'season_id' => $this->stagione->id,
            'home_team_id' => $this->avversaria->id,
            'away_team_id' => $this->savino->id,
            'location' => 'Pala BigMat - Firenze',
        ]);

        $luogo = app(DatiStrutturati::class)->gara($derby, 'it')['location'];

        $this->assertSame('50142', $luogo['address']['postalCode']);
    }

    private function paginaDelPalazzetto(): void
    {
        Page::updateOrCreate(['slug' => 'palazzetto'], [
            'title' => ['it' => 'Il Palazzetto', 'en' => 'The Arena'],
            'template' => 'Public/Societa/Palazzetto',
            'status' => PostStatus::Published,
            'content_data' => ['it' => [
                'venue_name' => 'Pala BigMat',
                'venue_address' => 'Via del Cavallaccio, 18/20/22/24 — 50142 Firenze (FI)',
            ]],
        ]);
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
    public function il_prodotto_dichiara_spedizioni_delle_zone_e_reso_a_14_giorni(): void
    {
        ShippingZone::factory()->create([
            'countries' => ['IT'], 'flat_rate' => 7.5, 'free_threshold' => 100,
            'weight_rates' => [], 'estimated_days_min' => 2, 'estimated_days_max' => 7, 'is_active' => true, 'sort_order' => 1,
        ]);
        ShippingZone::factory()->create([
            'countries' => ['FR', 'DE'], 'flat_rate' => 17, 'free_threshold' => null,
            'weight_rates' => [], 'estimated_days_min' => 3, 'estimated_days_max' => 5, 'is_active' => true, 'sort_order' => 2,
        ]);
        // Il resto del mondo non ha paesi da dichiarare.
        ShippingZone::factory()->create(['countries' => ['*'], 'flat_rate' => 50, 'is_active' => true, 'sort_order' => 3]);
        $prodotto = Product::factory()->create(['slug' => 'sciarpa', 'price' => 20, 'stock' => 5, 'is_active' => true]);

        $offerta = $this->delTipo($this->blocchi('/shop/prodotto/'.$prodotto->slug), 'Product')['offers'];

        $this->assertCount(2, $offerta['shippingDetails']);
        $italia = $offerta['shippingDetails'][0];
        $this->assertSame('7.50', $italia['shippingRate']['value']);
        $this->assertSame('IT', $italia['shippingDestination'][0]['addressCountry']);
        $this->assertSame(2, $italia['deliveryTime']['transitTime']['minValue']);
        $this->assertSame(7, $italia['deliveryTime']['transitTime']['maxValue']);
        $this->assertSame(['FR', 'DE'], array_column($offerta['shippingDetails'][1]['shippingDestination'], 'addressCountry'));

        $reso = $offerta['hasMerchantReturnPolicy'];
        $this->assertSame(14, $reso['merchantReturnDays']);
        $this->assertSame(['IT', 'FR', 'DE'], $reso['applicableCountry']);
        $this->assertSame('https://schema.org/MerchantReturnFiniteReturnWindow', $reso['returnPolicyCategory']);
    }

    #[Test]
    public function il_prodotto_con_le_taglie_e_un_gruppo_con_lo_sku_di_ogni_taglia(): void
    {
        $prodotto = Product::factory()->create(['slug' => 'maglia', 'type' => ProductType::Variable, 'price' => 40, 'sku' => null, 'is_active' => true]);
        ProductVariant::factory()->create(['product_id' => $prodotto->id, 'size' => 'S', 'sku' => 'MAGLIA-S', 'stock' => 2, 'price_modifier' => 0]);
        ProductVariant::factory()->create(['product_id' => $prodotto->id, 'size' => 'XL', 'sku' => 'MAGLIA-XL', 'stock' => 0, 'price_modifier' => 5]);

        $gruppo = $this->delTipo($this->blocchi('/shop/prodotto/'.$prodotto->slug), 'ProductGroup');

        $this->assertSame((string) $prodotto->id, $gruppo['productGroupID']);
        $this->assertSame(['MAGLIA-S', 'MAGLIA-XL'], array_column($gruppo['hasVariant'], 'sku'));
        $this->assertSame('45.00', $gruppo['hasVariant'][1]['offers']['price']);
        $this->assertSame('https://schema.org/OutOfStock', $gruppo['hasVariant'][1]['offers']['availability']);
        $this->assertArrayNotHasKey('offers', $gruppo);
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

        $indirizzo = '/stagione/atleta/'.$atleta->id.'-ekaterina-antropova';
        $blocchi = $this->blocchi($indirizzo);
        $persona = $this->delTipo($blocchi, 'Person');

        $this->haICampi($persona, ['name', 'url', 'jobTitle', 'memberOf', 'nationality', 'height']);
        $this->assertSame('SportsTeam', $persona['memberOf']['@type']);
        $this->assertSame('Savino Del Bene Volley', $persona['memberOf']['name']);
        $this->assertArrayNotHasKey('birthDate', $persona);

        $briciole = $this->delTipo($blocchi, 'BreadcrumbList')['itemListElement'];
        $this->assertSame(url('/stagione'), $briciole[1]['item']);
        $this->assertStringStartsWith('Ekaterina Antropova', $briciole[2]['name']);

        // Il titolo sta nell'HTML servito, non lo scrive Vue.
        $html = (string) $this->get($indirizzo)->getContent();
        $this->assertMatchesRegularExpression('#<title[^>]*>Ekaterina Antropova — [^<|]+ \| Savino Del Bene Volley</title>#u', $html);
        $this->assertMatchesRegularExpression('#<meta name="description" content="Ekaterina Antropova[^"]+"#u', $html);
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

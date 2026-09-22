<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Services\NewsFeedBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Il feed RSS delle notizie, che la Lega Pallavolo Serie A Femminile riprende
 * per la rassegna delle società.
 */
class NewsFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function feed(string $percorso = '/feed'): \SimpleXMLElement
    {
        $risposta = $this->get($percorso);
        $risposta->assertOk();

        $xml = simplexml_load_string($risposta->getContent());

        $this->assertNotFalse($xml, 'Il feed non è XML valido.');

        return $xml;
    }

    public function test_il_feed_risponde_come_rss(): void
    {
        $risposta = $this->get('/feed');

        $risposta->assertOk();
        $risposta->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');
        $this->assertStringStartsWith('<?xml', trim($risposta->getContent()));
    }

    public function test_il_feed_elenca_solo_le_notizie_pubblicate(): void
    {
        Post::factory()->create(['title' => 'Comunicato pubblicato']);
        Post::factory()->draft()->create(['title' => 'Bozza da non pubblicare']);
        Post::factory()->create([
            'title' => 'Programmata per domani',
            'status' => PostStatus::Published,
            'published_at' => now()->addDay(),
        ]);

        $xml = $this->feed();

        $titoli = array_map(
            fn ($item) => (string) $item->title,
            iterator_to_array($xml->channel->item, false)
        );

        $this->assertSame(['Comunicato pubblicato'], $titoli);
    }

    public function test_le_notizie_sono_in_ordine_di_pubblicazione(): void
    {
        Post::factory()->create(['title' => 'La più vecchia', 'published_at' => now()->subDays(3)]);
        Post::factory()->create(['title' => 'La più recente', 'published_at' => now()->subHour()]);
        Post::factory()->create(['title' => 'Quella di mezzo', 'published_at' => now()->subDay()]);

        $xml = $this->feed();

        $titoli = array_map(
            fn ($item) => (string) $item->title,
            iterator_to_array($xml->channel->item, false)
        );

        $this->assertSame(['La più recente', 'Quella di mezzo', 'La più vecchia'], $titoli);
    }

    public function test_il_feed_si_ferma_al_numero_di_notizie_previsto(): void
    {
        Post::factory()->count(NewsFeedBuilder::NUMERO_DI_NOTIZIE + 5)->create();

        $xml = $this->feed();

        $this->assertCount(NewsFeedBuilder::NUMERO_DI_NOTIZIE, iterator_to_array($xml->channel->item, false));
    }

    public function test_la_notizia_porta_indirizzo_guid_data_e_categoria(): void
    {
        $categoria = Category::factory()->create(['name' => 'Prima Squadra']);
        $notizia = Post::factory()->create([
            'title' => 'Vittoria al tie-break',
            'slug' => 'vittoria-al-tie-break',
            'excerpt' => 'Tre punti pesanti al PalaBigMat.',
            'published_at' => now()->subDay(),
        ]);
        $notizia->categories()->attach($categoria);

        $item = $this->feed()->channel->item[0];

        $this->assertSame(url('/news/vittoria-al-tie-break'), (string) $item->link);
        $this->assertSame('urn:savinodelbenevolley:notizia:'.$notizia->id, (string) $item->guid);
        $this->assertSame('false', (string) $item->guid['isPermaLink']);
        $this->assertSame($notizia->published_at->toRfc2822String(), (string) $item->pubDate);
        $this->assertSame('Prima Squadra', (string) $item->category);
        $this->assertStringContainsString('Tre punti pesanti', (string) $item->description);
    }

    public function test_il_contenuto_completo_viaggia_in_content_encoded_con_indirizzi_assoluti(): void
    {
        Post::factory()->create([
            'content' => '<p>Le foto: <img src="/storage/news/2026/09/foto.jpg"> e il <a href="/stagione">roster</a>.</p>',
        ]);

        $item = $this->feed()->channel->item[0];
        $contenuto = (string) $item->children('content', true)->encoded;

        $this->assertStringContainsString('src="'.url('/storage/news/2026/09/foto.jpg').'"', $contenuto);
        $this->assertStringContainsString('href="'.url('/stagione').'"', $contenuto);
        $this->assertStringNotContainsString('src="/storage', $contenuto);
    }

    public function test_gli_indirizzi_gia_assoluti_restano_intatti(): void
    {
        Post::factory()->create([
            'content' => '<p><img src="https://cdn.esempio.it/foto.jpg"><a href="//altro.esempio.it/pagina">link</a></p>',
        ]);

        $contenuto = (string) $this->feed()->channel->item[0]->children('content', true)->encoded;

        $this->assertStringContainsString('src="https://cdn.esempio.it/foto.jpg"', $contenuto);
        $this->assertStringContainsString('href="//altro.esempio.it/pagina"', $contenuto);
    }

    public function test_la_descrizione_ripiega_sul_contenuto_quando_manca_l_occhiello(): void
    {
        Post::factory()->create([
            'excerpt' => '',
            'content' => '<p>Il ritiro precampionato comincia lunedì.</p>',
        ]);

        $descrizione = (string) $this->feed()->channel->item[0]->description;

        $this->assertStringContainsString('Il ritiro precampionato comincia lunedì.', $descrizione);
        $this->assertStringNotContainsString('<p>', $descrizione);
    }

    public function test_un_contenuto_che_chiude_il_cdata_non_tronca_il_feed(): void
    {
        Post::factory()->create([
            'title' => 'Formula del punteggio',
            'content' => '<p>Nel codice: if (a[b[0]]> 1) { … }</p>',
        ]);

        $xml = $this->feed();

        $this->assertSame('Formula del punteggio', (string) $xml->channel->item[0]->title);
        $this->assertStringContainsString(
            'if (a[b[0]]> 1)',
            (string) $xml->channel->item[0]->children('content', true)->encoded
        );
    }

    public function test_i_caratteri_di_controllo_non_rompono_il_feed(): void
    {
        Post::factory()->create([
            'title' => "Titolo\x0Bcon un carattere vietato",
            'content' => "<p>Testo\x0Ccon un altro</p>",
        ]);

        $xml = $this->feed();

        $this->assertSame('Titolocon un carattere vietato', (string) $xml->channel->item[0]->title);
    }

    public function test_le_righe_storiche_in_testo_semplice_non_spariscono(): void
    {
        // I contenuti importati da WordPress hanno il titolo in testo semplice
        // invece del JSON per lingua: spatie da solo restituirebbe una stringa
        // vuota e il feed elencherebbe notizie senza titolo.
        $notizia = Post::factory()->create(['slug' => 'comunicato-storico']);
        DB::table('posts')->where('id', $notizia->id)->update([
            'title' => 'Comunicato del 2019',
            'content' => 'Testo senza traduzioni.',
            'excerpt' => '',
        ]);

        $item = $this->feed()->channel->item[0];

        $this->assertSame('Comunicato del 2019', (string) $item->title);
        $this->assertStringContainsString('Testo senza traduzioni.', (string) $item->description);
    }

    public function test_il_canale_dichiara_se_stesso_e_punta_alle_news(): void
    {
        Post::factory()->create();

        $canale = $this->feed()->channel;

        $this->assertSame(url('/news'), (string) $canale->link);
        $this->assertSame('it-IT', (string) $canale->language);
        $this->assertSame(
            url('/feed'),
            (string) $canale->children('atom', true)->link->attributes()->href
        );
    }

    public function test_il_feed_inglese_usa_gli_indirizzi_inglesi(): void
    {
        Post::factory()->create(['slug' => 'press-release']);

        $canale = $this->feed('/en/feed')->channel;

        $this->assertSame('en-US', (string) $canale->language);
        $this->assertSame(url('/en/news'), (string) $canale->link);
        $this->assertSame(url('/en/feed'), (string) $canale->children('atom', true)->link->attributes()->href);
        $this->assertSame(url('/en/news/press-release'), (string) $canale->item[0]->link);
    }

    public function test_gli_indirizzi_vecchi_portano_al_feed(): void
    {
        $this->get('/news/feed')->assertRedirect(url('/feed'));
        $this->get('/rss')->assertRedirect(url('/feed'));
        $this->get('/en/news/feed')->assertRedirect(url('/en/feed'));
    }

    public function test_una_notizia_che_si_chiama_feed_non_ruba_l_indirizzo(): void
    {
        Post::factory()->create(['slug' => 'feed']);

        $this->get('/news/feed')->assertRedirect(url('/feed'));
    }

    public function test_salvando_una_notizia_il_feed_si_aggiorna(): void
    {
        Post::factory()->create(['title' => 'Prima versione']);

        $this->feed();

        Post::query()->first()->update(['title' => 'Versione corretta']);

        $this->assertSame('Versione corretta', (string) $this->feed()->channel->item[0]->title);
    }

    public function test_il_sito_dichiara_il_feed_nell_intestazione(): void
    {
        $this->get('/news')
            ->assertSee('type="application/rss+xml"', false)
            ->assertSee('href="'.url('/feed').'"', false);
    }

    public function test_il_guid_non_cambia_se_cambia_lo_slug(): void
    {
        $notizia = Post::factory()->create(['slug' => 'slug-di-partenza']);

        $guid = (string) $this->feed()->channel->item[0]->guid;

        $notizia->update(['slug' => 'slug-corretto-in-redazione']);

        $item = $this->feed()->channel->item[0];

        $this->assertSame($guid, (string) $item->guid);
        $this->assertSame(url('/news/slug-corretto-in-redazione'), (string) $item->link);
    }

    public function test_la_copertina_viaggia_come_allegato(): void
    {
        Storage::fake('public');

        $notizia = Post::factory()->create();
        $notizia->addMedia(UploadedFile::fake()->image('copertina.jpg', 1600, 900))
            ->toMediaCollection('cover', 'public');

        $item = $this->feed()->channel->item[0];

        $this->assertNotEmpty((string) $item->enclosure['url']);
        $this->assertSame('image/jpeg', (string) $item->enclosure['type']);
        $this->assertGreaterThan(0, (int) (string) $item->enclosure['length']);
        $this->assertSame('image', (string) $item->children('media', true)->content->attributes()->medium);

        // Il tipo dichiarato deve corrispondere al file servito: le
        // conversioni escono in JPG anche da un PNG.
        $perIlWeb = (string) $item->children('media', true)->content->attributes()->url;
        $this->assertStringEndsWith(
            str_contains($perIlWeb, '.png') ? 'png' : 'jpeg',
            (string) $item->children('media', true)->content->attributes()->type
        );
    }

    public function test_il_feed_resta_valido_senza_notizie(): void
    {
        $canale = $this->feed()->channel;

        $this->assertNotEmpty((string) $canale->title);
        $this->assertNotEmpty((string) $canale->description);
        $this->assertSame(url('/news'), (string) $canale->link);
        $this->assertCount(0, iterator_to_array($canale->item ?? [], false));
    }

    public function test_la_pagina_inglese_dichiara_il_feed_inglese(): void
    {
        // `app()->setLocale()` riscrive `config('app.locale')`: chi decide il
        // prefisso confrontando le due lingue crede sempre di essere in
        // italiano, e la pagina inglese rimandava al feed italiano.
        $this->get('/en/news')
            ->assertSee('href="'.url('/en/feed').'"', false);
    }
}

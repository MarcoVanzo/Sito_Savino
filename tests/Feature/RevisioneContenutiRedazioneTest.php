<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La revisione del 20 settembre 2026 sui contenuti inseriti dalla redazione:
 * link rotti, doppioni, date impossibili, versioni inglesi rimaste al seeder.
 */
class RevisioneContenutiRedazioneTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRAZIONE = '2026_09_20_100000_revisione_dei_contenuti_della_redazione';

    private function migrazione(): Migration
    {
        return require database_path('migrations/'.self::MIGRAZIONE.'.php');
    }

    /**
     * @param  array<string, mixed>  $attributi
     */
    private function pagina(string $slug, string $template, array $attributi): Page
    {
        DB::table('pages')->where('slug', $slug)->delete();

        return Page::factory()->create(array_merge([
            'slug' => $slug,
            'template' => $template,
            'status' => PostStatus::Published,
        ], $attributi));
    }

    /**
     * @return array<string, mixed>
     */
    private function contenuti(Page $pagina, string $lingua): array
    {
        return json_decode((string) DB::table('pages')->where('id', $pagina->id)->value('content_data'), true)[$lingua];
    }

    #[Test]
    public function in_inglese_un_elenco_vuoto_prende_quello_italiano(): void
    {
        $this->pagina('club-race', 'Public/ClubRace', [
            'content_data' => [
                'it' => ['standings' => [['club' => 'Sorms', 'points' => '12']], 'standings_title' => 'Classifica'],
                'en' => ['standings' => [], 'standings_title' => 'Standings'],
            ],
        ]);

        $this->get('/en/ticketing/club-race')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('page.content_data.standings.0.club', 'Sorms')
                // I testi restano quelli della lingua richiesta.
                ->where('page.content_data.standings_title', 'Standings'));
    }

    #[Test]
    public function in_inglese_link_e_immagini_vuoti_prendono_quelli_italiani_ma_i_testi_no(): void
    {
        $this->pagina('biglietteria', 'Public/Ticketing', [
            'content_data' => [
                'it' => ['feature_button_url' => 'https://savinodelbenevolley.vivaticket.it/', 'feature_button_text' => 'Acquista i biglietti', 'gift_card_url' => 'https://savinodelbenevolley.vivaticket.it/it/vivacard/prices'],
                'en' => ['feature_button_url' => null, 'feature_button_text' => null, 'gift_card_url' => 'https://example.org/en-gift-card'],
            ],
        ]);

        $this->get('/en/ticketing/biglietteria')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->where('page.content_data.feature_button_url', 'https://savinodelbenevolley.vivaticket.it/')
                // Un valore inglese compilato vince; un testo vuoto resta vuoto.
                ->where('page.content_data.gift_card_url', 'https://example.org/en-gift-card')
                ->where('page.content_data.feature_button_text', null));
    }

    #[Test]
    public function un_elenco_compilato_in_inglese_non_viene_sostituito(): void
    {
        $this->pagina('club-race', 'Public/ClubRace', [
            'content_data' => [
                'it' => ['standings' => [['club' => 'Sorms', 'points' => '12']]],
                'en' => ['standings' => [['club' => 'Barga', 'points' => '3']]],
            ],
        ]);

        $this->get('/en/ticketing/club-race')
            ->assertInertia(fn ($pagina) => $pagina->where('page.content_data.standings.0.club', 'Barga'));
    }

    #[Test]
    public function il_pulsante_di_hospitality_con_la_sola_email_diventa_un_mailto(): void
    {
        $pagina = $this->pagina('hospitality', 'Public/ContentPage', [
            'content_data' => ['it' => ['button_url' => 'marketing@savinodelbenevolley.it', 'button_text' => 'Contattaci']],
        ]);

        $this->migrazione()->up();

        $this->assertSame('mailto:marketing@savinodelbenevolley.it', $this->contenuti($pagina, 'it')['button_url']);
    }

    #[Test]
    public function la_club_race_non_dichiara_un_aggiornamento_nel_futuro(): void
    {
        $pagina = $this->pagina('club-race', 'Public/ClubRace', [
            'content' => ['it' => '<h2>Regolamento</h2><p>Testo</p><h2><strong>1° Classificato</strong></h2><p>Premio</p><h2><strong>2° Classificato</strong></h2><p>Premio</p>'],
            'content_data' => ['it' => [
                'hero_label' => 'Società territorio',
                'standings' => [['club' => 'Sorms', 'points' => '0'], ['club' => 'Barga', 'points' => '0']],
                'standings_updated' => '4 ottobre 2026',
                'standings_note' => null,
            ]],
        ]);

        $this->migrazione()->up();

        $dati = $this->contenuti($pagina, 'it');
        $this->assertSame('Ticketing', $dati['hero_label']);
        $this->assertNull($dati['standings_updated']);
        $this->assertStringContainsString('4 ottobre 2026', $dati['standings_note']);

        $testo = json_decode((string) DB::table('pages')->where('id', $pagina->id)->value('content'), true)['it'];
        $this->assertStringContainsString('<h2>Premi</h2><h3>1° Classificato</h3>', $testo);
        $this->assertStringContainsString('<h3>2° Classificato</h3>', $testo);
    }

    #[Test]
    public function una_classifica_gia_partita_non_si_tocca(): void
    {
        $pagina = $this->pagina('club-race', 'Public/ClubRace', [
            'content_data' => ['it' => [
                'standings' => [['club' => 'Sorms', 'points' => '18']],
                'standings_updated' => '4 ottobre 2026',
            ]],
        ]);

        $this->migrazione()->up();

        $this->assertSame('4 ottobre 2026', $this->contenuti($pagina, 'it')['standings_updated']);
    }

    #[Test]
    public function il_secondo_vantaggi_degli_abbonamenti_sparisce_e_l_inglese_riceve_il_link(): void
    {
        $pagina = $this->pagina('abbonamenti', 'Public/Ticketing', [
            'content_data' => [
                'it' => ['benefits_heading' => 'Vantaggi', 'boxoffice_title' => 'Vantaggi', 'boxoffice_description' => 'Sfrutta tutti i vantaggi', 'tickets_url' => 'https://savinodelbenevolley.vivaticket.it/abbonamento'],
                'en' => ['benefits_heading' => 'Benefits', 'boxoffice_title' => 'At the Box Office', 'boxoffice_description' => 'The Pala BigMat box office opens 2 hours before.', 'tickets_url' => null, 'tickets_button_text' => 'Buy tickets'],
            ],
        ]);

        $this->migrazione()->up();

        $this->assertNull($this->contenuti($pagina, 'it')['boxoffice_title']);
        $this->assertNull($this->contenuti($pagina, 'en')['boxoffice_title']);
        $this->assertSame('https://savinodelbenevolley.vivaticket.it/abbonamento', $this->contenuti($pagina, 'en')['tickets_url']);
    }

    #[Test]
    public function la_missione_del_seeder_resta_solo_sulla_pagina_d_insieme(): void
    {
        $missione = [
            'mission_badge' => 'La Nostra Missione',
            'mission_title' => 'Sport Come Strumento Sociale',
            'mission_text_1' => 'La Savino Del Bene crede fermamente nel potere trasformativo dello sport. Attraverso i nostri progetti sociali…',
            'mission_text_2' => 'Dalla pallavolo per tutti ai programmi educativi, dal sitting volley alle iniziative ambientali.',
        ];

        $progetto = $this->pagina('volley-4-all', 'Public/Sociale', ['content_data' => ['it' => $missione]]);
        $insieme = $this->pagina('progetti-sociali', 'Public/Sociale', ['content_data' => ['it' => $missione + [
            'projects' => [['title' => 'Volley4All', 'link' => null, 'contact_email' => null], ['title' => 'Volley4kids', 'link' => null, 'contact_email' => null]],
        ]]]);

        $this->migrazione()->up();

        $this->assertNull($this->contenuti($progetto, 'it')['mission_text_1']);

        $dati = $this->contenuti($insieme, 'it');
        $this->assertSame($missione['mission_text_1'], $dati['mission_text_1']);
        $this->assertSame('Volley 4 All', $dati['projects'][0]['title']);
        $this->assertSame('/sociale/volley-4-all', $dati['projects'][0]['link']);
        // Volley4kids non ha una pagina sua: resta senza link.
        $this->assertNull($dati['projects'][1]['link']);
    }

    #[Test]
    public function una_missione_riscritta_dalla_redazione_non_si_tocca(): void
    {
        $pagina = $this->pagina('volley-4-all', 'Public/Sociale', [
            'content_data' => ['it' => ['mission_title' => 'Perché lo facciamo', 'mission_text_1' => 'Un testo scritto in redazione.']],
        ]);

        $this->migrazione()->up();

        $this->assertSame('Un testo scritto in redazione.', $this->contenuti($pagina, 'it')['mission_text_1']);
    }

    #[Test]
    public function il_titolo_ripetuto_in_cima_al_testo_viene_tolto_solo_se_identico(): void
    {
        $uguale = $this->pagina('convenzioni', 'Public/Convenzioni', [
            'title' => ['it' => 'Convenzioni'],
            'content' => ['it' => '<h2>Convenzioni</h2><p>Testo.</p>'],
        ]);
        $diverso = $this->pagina('biglietteria', 'Public/Ticketing', [
            'title' => ['it' => 'Biglietteria'],
            'content' => ['it' => '<h2>Dove si comprano</h2><p>Testo.</p>'],
        ]);

        $this->migrazione()->up();

        $this->assertSame('<p>Testo.</p>', json_decode((string) DB::table('pages')->where('id', $uguale->id)->value('content'), true)['it']);
        $this->assertSame('<h2>Dove si comprano</h2><p>Testo.</p>', json_decode((string) DB::table('pages')->where('id', $diverso->id)->value('content'), true)['it']);
    }

    #[Test]
    public function le_tappe_di_giugno_non_restano_disponibili(): void
    {
        $pagina = $this->pagina('talent-day', 'Public/TalentDay', [
            'content_data' => ['it' => ['stages' => [
                ['date' => '5 giugno', 'place' => 'Udine (UD)', 'status' => 'Disponibile', 'sold_out' => false],
                ['date' => '12 ottobre', 'place' => 'Scandicci (FI)', 'status' => 'Disponibile', 'sold_out' => false],
            ]]],
        ]);

        $this->migrazione()->up();

        $tappe = $this->contenuti($pagina, 'it')['stages'];
        $this->assertSame('Conclusa', $tappe[0]['status']);
        $this->assertTrue($tappe[0]['sold_out']);
        $this->assertSame('Disponibile', $tappe[1]['status']);
    }

    #[Test]
    public function il_safeguarding_pubblica_anche_i_protocolli_che_stavano_solo_nel_footer(): void
    {
        DB::table('site_settings')->whereIn('key', ['legal.protocollo_bullismo', 'legal.protocollo_razzismo'])->delete();
        DB::table('site_settings')->insert([
            ['key' => 'legal.protocollo_bullismo', 'value' => 'legal/Protocollo-2-Bullismo-e-cyberbullismo.pdf', 'type' => 'text', 'group' => 'general'],
            // Il secondo non è caricato: non si pubblica un link verso il niente.
            ['key' => 'legal.protocollo_razzismo', 'value' => '', 'type' => 'text', 'group' => 'general'],
        ]);

        $pagina = $this->pagina('safeguarding', 'Public/Societa/Safeguarding', [
            'content_data' => [
                'it' => ['documents' => [['file' => 'safeguarding/Modello.pdf', 'icon' => 'M1', 'title' => 'Modello Organizzativo', 'description' => 'Testo']]],
                'en' => ['documents' => [['file' => null, 'icon' => 'M1', 'title' => 'Organizational Model', 'description' => 'Text']]],
            ],
        ]);

        $this->migrazione()->up();
        $this->migrazione()->up();

        $italiani = $this->contenuti($pagina, 'it')['documents'];
        $this->assertTrue(array_is_list($italiani));
        $this->assertCount(2, $italiani);
        $this->assertSame('legal/Protocollo-2-Bullismo-e-cyberbullismo.pdf', $italiani[1]['file']);
        $this->assertSame('Protocollo Bullismo e Cyberbullismo', $italiani[1]['title']);

        $inglesi = $this->contenuti($pagina, 'en')['documents'];
        $this->assertCount(2, $inglesi);
        // La scheda inglese senza file prende il PDF di quella italiana.
        $this->assertSame('safeguarding/Modello.pdf', $inglesi[0]['file']);
        $this->assertSame('Anti-Bullying and Cyberbullying Protocol', $inglesi[1]['title']);
    }

    #[Test]
    public function rilanciarla_non_cambia_nulla(): void
    {
        $pagina = $this->pagina('double-face', 'Public/ContentPage', [
            'content_data' => ['it' => ['video_url' => 'https://www.youtube.com/@savinodelbenevolley1771', 'button_url' => 'https://www.youtube.com/@savinodelbenevolley1771']],
        ]);

        $this->migrazione()->up();
        $prima = DB::table('pages')->where('id', $pagina->id)->value('content_data');
        $this->migrazione()->up();

        $this->assertNull($this->contenuti($pagina, 'it')['video_url']);
        $this->assertSame($prima, DB::table('pages')->where('id', $pagina->id)->value('content_data'));
    }
}

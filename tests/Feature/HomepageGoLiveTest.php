<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Evento;
use App\Models\Game;
use App\Models\SiteSetting;
use App\Models\Sponsor;
use App\Models\Team;
use App\Support\MatchDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le funzioni della homepage chieste dalla redazione per il go-live: Match Day
 * con il pop-up dei biglietti, striscia degli sponsor e spazio Eventi.
 */
class HomepageGoLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00', 'Europe/Rome'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function garaDiOggi(bool $inCasa, array $attributi = []): Game
    {
        $savino = Team::factory()->internal()->create(['name' => 'Savino Del Bene']);
        $avversaria = Team::factory()->create(['name' => 'Avversaria']);

        return Game::factory()->create([
            'home_team_id' => $inCasa ? $savino->id : $avversaria->id,
            'away_team_id' => $inCasa ? $avversaria->id : $savino->id,
            'match_date' => now()->setTime(20, 30),
            'status' => GameStatus::Scheduled,
            'competition_type' => 'Campionato',
            ...$attributi,
        ]);
    }

    private function modalita(string $valore, ?string $accesoIl = null): void
    {
        SiteSetting::set('match_day_modalita', $valore, 'home');
        SiteSetting::set('match_day_acceso_il', $accesoIl ?? now()->toDateString(), 'home');
    }

    #[Test]
    public function in_automatico_si_accende_nel_giorno_di_una_gara_in_casa_con_il_pop_up(): void
    {
        $this->garaDiOggi(inCasa: true);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('matchDay.attivo', true)
            ->where('matchDay.inCasa', true)
            ->where('matchDay.gara.home_team.name', 'Savino Del Bene')
            ->where('matchDay.popup.titolo', 'Oggi si gioca!')
        );
    }

    #[Test]
    public function in_trasferta_c_e_la_fascia_ma_non_il_pop_up_dei_biglietti(): void
    {
        $this->garaDiOggi(inCasa: false);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('matchDay.attivo', true)
            ->where('matchDay.inCasa', false)
            ->where('matchDay.popup', null)
        );
    }

    #[Test]
    public function la_gara_gia_cominciata_tiene_accesa_la_modalita_ma_non_vende_piu_biglietti(): void
    {
        $gara = $this->garaDiOggi(inCasa: true, attributi: [
            'match_date' => now()->setTime(10, 0),
            'status' => GameStatus::InProgress,
        ]);

        $stato = MatchDay::stato();
        $this->assertTrue($stato['attivo']);
        $this->assertNull($stato['popup']);

        // Tre ore dopo l'inizio la gara è finita: la home torna quella di sempre.
        $gara->update(['match_date' => now()->setTime(8, 59), 'status' => GameStatus::Completed]);
        $this->assertFalse(MatchDay::stato()['attivo']);
    }

    #[Test]
    public function senza_gara_oggi_una_rinviata_o_con_la_modalita_spenta_resta_tutto_spento(): void
    {
        $this->assertFalse(MatchDay::stato()['attivo']);

        $gara = $this->garaDiOggi(inCasa: true, attributi: ['status' => GameStatus::Postponed]);
        $this->assertFalse(MatchDay::stato()['attivo']);

        $gara->update(['status' => GameStatus::Scheduled]);
        $this->modalita(MatchDay::SPENTO);
        $this->assertFalse(MatchDay::stato()['attivo']);
    }

    #[Test]
    public function accesa_a_mano_vale_anche_senza_gara_e_il_pop_up_si_spegne_da_solo(): void
    {
        $this->modalita(MatchDay::ACCESO);

        $stato = MatchDay::stato();
        $this->assertTrue($stato['attivo']);
        $this->assertNull($stato['gara']);
        $this->assertNotNull($stato['popup']);

        SiteSetting::set('match_day_popup_attivo', false, 'home');
        $this->assertNull(MatchDay::stato()['popup']);
    }

    #[Test]
    public function una_gara_serale_resta_in_pagina_anche_dopo_mezzanotte(): void
    {
        $this->garaDiOggi(inCasa: true, attributi: ['match_date' => now()->setTime(21, 30)]);

        Carbon::setTestNow(Carbon::parse('2026-10-05 00:15', 'Europe/Rome'));
        $this->assertTrue(MatchDay::stato()['attivo']);

        Carbon::setTestNow(Carbon::parse('2026-10-05 00:31', 'Europe/Rome'));
        $this->assertFalse(MatchDay::stato()['attivo']);
    }

    #[Test]
    public function accesa_a_mano_ieri_oggi_torna_automatica(): void
    {
        $this->modalita(MatchDay::ACCESO, accesoIl: now()->subDay()->toDateString());

        $this->assertSame(MatchDay::AUTO, MatchDay::modalita());
        $this->assertFalse(MatchDay::stato()['attivo']);
    }

    #[Test]
    public function il_link_dei_biglietti_della_redazione_arriva_anche_alla_fascia(): void
    {
        $this->garaDiOggi(inCasa: true);
        SiteSetting::set('match_day_popup_url', 'https://www.vivaticket.com/partita', 'home');

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('matchDay.urlBiglietti', 'https://www.vivaticket.com/partita')
        );
    }

    #[Test]
    public function il_pop_up_arriva_nella_lingua_della_pagina(): void
    {
        $this->garaDiOggi(inCasa: true);

        $this->get('/en')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('matchDay.popup.titolo', 'Match day!')
            ->where('matchDay.popup.pulsante', 'Buy tickets')
        );
    }

    #[Test]
    public function la_striscia_riceve_gli_sponsor_in_ordine_di_livello(): void
    {
        Sponsor::factory()->create(['name' => 'Fornitore', 'tier' => 'supplier', 'sort_order' => 0]);
        Sponsor::factory()->create(['name' => 'Title', 'tier' => 'title', 'sort_order' => 5]);
        Sponsor::factory()->create(['name' => 'Main', 'tier' => 'main', 'sort_order' => 0]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sponsor', 3)
            ->where('sponsor.0.name', 'Title')
            ->where('sponsor.1.name', 'Main')
            ->where('sponsor.2.name', 'Fornitore')
        );
    }

    #[Test]
    public function gli_eventi_in_home_sono_i_prossimi_tre_pubblicati(): void
    {
        $crea = fn (string $titolo, string $inizio, array $altro = []) => Evento::create([
            'titolo' => ['it' => $titolo, 'en' => $titolo.' EN'],
            'inizia_il' => Carbon::parse($inizio, 'Europe/Rome'),
            'pubblicato' => true,
            ...$altro,
        ]);

        $crea('Ieri', '2026-10-03 18:00');
        $crea('Stamattina', '2026-10-04 09:00');
        $crea('Finito', '2026-10-01 09:00', ['finisce_il' => Carbon::parse('2026-10-04 11:00', 'Europe/Rome')]);
        $crea('In corso', '2026-10-01 09:00', ['finisce_il' => Carbon::parse('2026-10-05 18:00', 'Europe/Rome')]);
        $crea('Bozza', '2026-10-06 18:00', ['pubblicato' => false]);
        $crea('Presentazione', '2026-10-10 18:00');
        $crea('Festa', '2026-10-20 18:00');

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('eventi', 3)
            ->where('eventi.0.titolo', 'In corso')
            ->where('eventi.1.titolo', 'Stamattina')
            ->where('eventi.2.titolo', 'Presentazione')
        );

        $this->get('/en')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('eventi.2.titolo', 'Presentazione EN')
        );
    }

    #[Test]
    public function un_evento_scritto_solo_in_italiano_compare_anche_in_inglese(): void
    {
        Evento::create([
            'titolo' => ['it' => 'Festa dei tifosi'],
            'inizia_il' => now()->addDays(2),
            'pubblicato' => true,
        ]);

        $this->get('/en')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('eventi.0.titolo', 'Festa dei tifosi')
        );
    }
}

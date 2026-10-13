<?php

namespace Tests\Feature\Diretta;

use App\Enums\EventoDellaDiretta;
use App\Enums\UserRole;
use App\Enums\UsoCreativita;
use App\Filament\Pages\ReportSponsorDiretta;
use App\Models\Game;
use App\Models\LiveAdStat;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Models\SiteSetting;
use App\Models\Sponsor;
use App\Models\User;
use App\Services\Diretta\ReportDellaDiretta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportDegliSponsorDellaDirettaTest extends TestCase
{
    use RefreshDatabase;

    private Game $gara;

    private LiveCreative $spot;

    private LiveCreative $cornice;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set('diretta_sponsor_abilitata', '1');

        // Gli eventi contano solo attorno alla gara.
        $this->gara = Game::factory()->create(['match_date' => now()->subMinutes(30)]);
        $sponsor = Sponsor::factory()->create(['name' => 'Estra']);
        $this->spot = LiveCreative::factory()->create(['sponsor_id' => $sponsor->id, 'nome' => 'Spot 15"']);
        $this->cornice = LiveCreative::factory()->create(['sponsor_id' => $sponsor->id, 'nome' => 'Cornice', 'uso' => UsoCreativita::Cornice]);

        $sessione = LiveSession::perLaGara($this->gara);
        $sessione->ordina('creativitaDelPreroll', [$this->spot->id]);
        $sessione->ordina('creativitaDellaGara', [$this->cornice->id]);
    }

    private function evento(string $evento, ?int $creativita = null): TestResponse
    {
        return $this->postJson("/api/diretta/{$this->gara->id}/eventi", ['evento' => $evento, 'creativita' => $creativita ?? $this->spot->id]);
    }

    #[Test]
    public function ogni_evento_aggiunge_uno_alla_riga_del_giorno(): void
    {
        $this->evento('preroll_avviato')->assertNoContent();
        $this->evento('preroll_avviato')->assertNoContent();
        $this->evento('clic', $this->cornice->id)->assertNoContent();

        $this->assertSame(2, LiveAdStat::query()->where('evento', 'preroll_avviato')->value('conteggio'));
        $this->assertSame(1, LiveAdStat::query()->where('evento', 'clic')->value('conteggio'));
        $this->assertSame(2, LiveAdStat::count());
    }

    #[Test]
    public function l_incremento_e_atomico_anche_con_tanti_insieme(): void
    {
        foreach (range(1, 25) as $volta) {
            LiveAdStat::conta($this->gara->id, $this->cornice->id, EventoDellaDiretta::CicloMostrato);
        }

        $this->assertSame(25, LiveAdStat::query()->sole()->conteggio);
    }

    #[Test]
    public function la_riga_non_tiene_niente_del_visitatore(): void
    {
        $this->evento('preroll_completato')->assertNoContent();

        $this->assertSame(
            ['conteggio', 'created_at', 'creativita_nome', 'evento', 'game_id', 'giorno', 'id', 'live_creative_id', 'sponsor_nome', 'updated_at'],
            collect(array_keys(LiveAdStat::query()->sole()->getAttributes()))->sort()->values()->all(),
        );
    }

    #[Test]
    public function eventi_non_validi_e_creativita_estranee_si_rifiutano(): void
    {
        $this->evento('visualizzazione_inventata')->assertUnprocessable();
        $this->evento('clic', LiveCreative::factory()->create()->id)->assertUnprocessable();
        $this->postJson('/api/diretta/'.Game::factory()->create()->id.'/eventi', ['evento' => 'clic', 'creativita' => $this->spot->id])->assertNotFound();

        $this->assertSame(0, LiveAdStat::count());
    }

    #[Test]
    public function con_l_interruttore_spento_la_rotta_non_risponde(): void
    {
        SiteSetting::set('diretta_sponsor_abilitata', '0');

        $this->evento('clic')->assertNotFound();
    }

    #[Test]
    public function oltre_centoventi_al_minuto_per_indirizzo_si_ferma(): void
    {
        foreach (range(1, 120) as $volta) {
            $this->evento('ciclo_mostrato', $this->cornice->id)->assertNoContent();
        }

        $this->evento('ciclo_mostrato', $this->cornice->id)->assertTooManyRequests();
    }

    #[Test]
    public function fuori_dal_tempo_della_gara_o_dall_uso_della_grafica_non_si_conta(): void
    {
        // Un «completato» su una cornice, un ciclo su uno spot del pre-roll.
        $this->evento('preroll_completato', $this->cornice->id)->assertUnprocessable();
        $this->evento('ciclo_mostrato', $this->spot->id)->assertUnprocessable();

        // Pre-roll spento per la gara: i suoi eventi non sono possibili.
        LiveSession::query()->update(['preroll_attivo' => false]);
        $this->evento('preroll_avviato')->assertUnprocessable();

        // Tre giorni dopo la gara non guarda nessuno.
        $this->travel(3)->days();
        $this->evento('clic', $this->cornice->id)->assertUnprocessable();

        $this->assertSame(0, LiveAdStat::count());
    }

    #[Test]
    public function cancellare_la_grafica_o_lo_sponsor_non_cancella_il_report(): void
    {
        LiveAdStat::conta($this->gara->id, $this->spot->id, EventoDellaDiretta::PrerollAvviato);
        LiveAdStat::conta($this->gara->id, $this->cornice->id, EventoDellaDiretta::Clic);
        $sponsor = $this->spot->sponsor;

        $this->spot->delete();
        $righe = collect(app(ReportDellaDiretta::class)->righe(sponsor: $sponsor->id))->keyBy('creativita');
        $this->assertSame(1, $righe['Spot 15"']['preroll_avviati']);
        $this->assertSame('Estra', $righe['Spot 15"']['sponsor']);

        $sponsor->delete();
        $this->assertSame(0, LiveCreative::count());
        $righe = collect(app(ReportDellaDiretta::class)->righe())->keyBy('creativita');
        $this->assertSame(['Cornice', 'Spot 15"'], $righe->keys()->sort()->values()->all());
        $this->assertSame(1, $righe['Cornice']['clic']);
    }

    #[Test]
    public function il_report_mette_prima_le_gare_piu_recenti_secondo_la_data_vera(): void
    {
        $gennaio = Game::factory()->create(['match_date' => '2027-01-31 20:30:00']);
        $ottobre = Game::factory()->create(['match_date' => '2026-10-13 20:30:00']);
        LiveAdStat::conta($ottobre->id, $this->spot->id, EventoDellaDiretta::Clic);
        LiveAdStat::conta($gennaio->id, $this->spot->id, EventoDellaDiretta::Clic);

        // Come testo «31/01/2027» veniva prima di «13/10/2026».
        $this->assertSame(['31/01/2027 20:30', '13/10/2026 20:30'], array_column(app(ReportDellaDiretta::class)->righe(sponsor: $this->spot->sponsor_id), 'data'));
    }

    #[Test]
    public function il_report_dice_il_completamento_del_pre_roll_e_i_clic(): void
    {
        foreach (['preroll_avviato', 'preroll_avviato', 'preroll_avviato', 'preroll_avviato', 'preroll_completato', 'preroll_completato', 'preroll_completato', 'preroll_saltato'] as $evento) {
            LiveAdStat::conta($this->gara->id, $this->spot->id, EventoDellaDiretta::from($evento));
        }
        LiveAdStat::conta($this->gara->id, $this->cornice->id, EventoDellaDiretta::Clic);

        $righe = collect(app(ReportDellaDiretta::class)->righe($this->gara->id))->keyBy('creativita');

        $this->assertSame(4, $righe['Spot 15"']['preroll_avviati']);
        $this->assertSame(75.0, $righe['Spot 15"']['completamento']);
        $this->assertSame(1, $righe['Cornice']['clic']);
        $this->assertNull($righe['Cornice']['completamento']);
    }

    #[Test]
    public function il_csv_si_apre_in_excel_e_si_scarica_dal_pannello(): void
    {
        LiveAdStat::conta($this->gara->id, $this->spot->id, EventoDellaDiretta::PrerollAvviato);
        $report = app(ReportDellaDiretta::class);

        $csv = $report->csv($report->righe());
        $this->assertStringStartsWith("\u{FEFF}Gara;Data;Sponsor;", $csv);
        $this->assertStringContainsString(';Estra;"Spot 15""";1;0;0;0,0;0;0;0', $csv);

        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::CommunicationManager, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        Livewire::test(ReportSponsorDiretta::class)
            ->assertSee('Spot 15"')
            ->callAction('esporta')
            ->assertFileDownloaded('report-sponsor-diretta-'.now()->format('Y-m-d').'.csv');
    }

    #[Test]
    public function nel_csv_un_nome_che_sembra_una_formula_resta_testo(): void
    {
        $this->spot->update(['nome' => '=HYPERLINK("http://example.com";"x")']);
        LiveAdStat::conta($this->gara->id, $this->spot->id, EventoDellaDiretta::Clic);
        $report = app(ReportDellaDiretta::class);

        $csv = $report->csv($report->righe());

        $this->assertStringContainsString(';"\'=HYPERLINK(', $csv);
    }
}

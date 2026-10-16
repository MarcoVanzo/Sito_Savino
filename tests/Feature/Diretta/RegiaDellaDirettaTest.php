<?php

namespace Tests\Feature\Diretta;

use App\Enums\GameStatus;
use App\Enums\StatoDellaDiretta;
use App\Enums\TipoCreativita;
use App\Enums\UserRole;
use App\Enums\UsoCreativita;
use App\Filament\Pages\RegiaDiretta;
use App\Jobs\RipubblicaLoStatoDelleDirette;
use App\Models\Game;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Models\User;
use App\Services\Diretta\RegiaDellaDiretta;
use App\Services\Diretta\StatoPubblicoDellaDiretta;
use App\Support\Diretta\DirettaPerIlSito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La regia cambia lo stato, alza la versione e riscrive il JSON letto dal
 * sito; senza regia il JSON dice solo i parametri del ciclo, che il browser
 * conta da sé.
 */
class RegiaDellaDirettaTest extends TestCase
{
    use RefreshDatabase;

    private User $regista;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        SiteSetting::set('diretta_sponsor_abilitata', '1');
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(17, 30));

        $this->regista = User::factory()->create();
        $this->regista->forceFill(['role' => UserRole::CommunicationManager, 'is_active' => true])->save();
    }

    private function sessione(): LiveSession
    {
        $gara = Game::factory()->create([
            'home_team_id' => Team::factory()->create(['is_internal' => true])->id,
            'away_team_id' => Team::factory()->create(['is_internal' => false])->id,
            'match_date' => now()->setTime(17, 0),
            'status' => GameStatus::Scheduled,
            'stream_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $sessione = LiveSession::perLaGara($gara);
        $sessione->ordina('creativitaDellaGara', [$this->cornice()->id, $this->cornice()->id]);

        return $sessione->refresh();
    }

    private function cornice(): LiveCreative
    {
        $cornice = LiveCreative::factory()->create(['uso' => UsoCreativita::Cornice, 'tipo' => TipoCreativita::Immagine]);
        ob_start();
        imagepng(imagecreatetruecolor(1920, 1080));
        $cornice->addMedia(UploadedFile::fake()->createWithContent('cornice.png', (string) ob_get_clean()))
            ->toMediaCollection(LiveCreative::COLLEZIONE_CONTENUTO);

        return $cornice;
    }

    /** @return array<string, mixed> */
    private function statoScritto(LiveSession $sessione): array
    {
        return json_decode(Storage::disk('public')->get("diretta/{$sessione->game_id}.json"), true);
    }

    private function regia(): RegiaDellaDiretta
    {
        return app(RegiaDellaDiretta::class);
    }

    #[Test]
    public function il_json_dice_ciclo_e_rotazione_senza_dati_dei_visitatori(): void
    {
        $sessione = $this->sessione();

        $this->assertTrue(app(StatoPubblicoDellaDiretta::class)->pubblica($sessione));
        $json = $this->statoScritto($sessione);

        $this->assertSame('in_onda', $json['stato']);
        $this->assertSame(10, $json['ciclo']['ogni_minuti']);
        $this->assertSame('2026-10-18T17:05:00+02:00', $json['ciclo']['inizio']);
        $this->assertSame('2026-10-18T20:00:00+02:00', $json['ciclo']['fine']);
        $this->assertCount(2, $json['playlist']);
        $this->assertNull($json['contenuto']);
    }

    #[Test]
    public function la_pausa_tra_i_set_alza_la_versione_e_dura_quanto_deciso(): void
    {
        $sessione = $this->sessione();
        $prima = $sessione->versione;

        $this->assertTrue($this->regia()->pausaTraISet($sessione));

        $json = $this->statoScritto($sessione);
        $this->assertSame('pausa', $json['stato']);
        $this->assertSame($prima + 1, $json['versione']);
        $this->assertSame('2026-10-18T17:30:00+02:00', $json['dalle']);
        $this->assertSame('2026-10-18T17:33:00+02:00', $json['fino_alle']);
        $this->assertNull($json['contenuto']);
    }

    #[Test]
    public function mostra_manda_in_onda_una_cornice_scelta_per_i_secondi_indicati(): void
    {
        $sessione = $this->sessione();
        $scelta = $sessione->creativitaDellaGara->last();

        $this->regia()->mostra($sessione, $scelta, 45);

        $json = $this->statoScritto($sessione);
        $this->assertSame($scelta->id, $json['contenuto']['id']);
        $this->assertSame(45, $json['contenuto']['durata']);
        $this->assertSame('2026-10-18T17:30:45+02:00', $json['fino_alle']);
    }

    #[Test]
    public function spegni_tutto_ferma_ciclo_e_spot_e_riaccendi_torna_in_onda(): void
    {
        $sessione = $this->sessione();
        $this->regia()->pausaTraISet($sessione);

        $this->regia()->spegniTutto($sessione);
        $json = $this->statoScritto($sessione);
        $this->assertSame('inattiva', $json['stato']);
        $this->assertFalse($json['ciclo']['attivo']);

        // Riaccendere riporta anche il ciclo: senza, nessuna cornice fino a fine gara.
        $this->regia()->riaccendi($sessione);
        $json = $this->statoScritto($sessione);
        $this->assertSame('in_onda', $json['stato']);
        $this->assertTrue($json['ciclo']['attivo']);
    }

    #[Test]
    public function sospendere_due_volte_non_riprende_il_ciclo(): void
    {
        $sessione = $this->sessione();
        $this->actingAs($this->regista);

        Livewire::test(RegiaDiretta::class)
            ->callAction('sospendiCiclo')
            ->assertActionHidden('sospendiCiclo')
            ->assertActionVisible('riprendiCiclo');

        // Il secondo tocco arrivato prima del ridisegno sospende di nuovo.
        $this->regia()->sospendiCiclo($sessione);
        $this->assertFalse($sessione->refresh()->ciclo_attivo);
    }

    #[Test]
    public function con_le_cornici_tutte_spente_la_pausa_tra_i_set_non_si_puo_mandare(): void
    {
        $sessione = $this->sessione();
        $sessione->creativitaDellaGara->each->update(['attiva' => false]);
        $this->actingAs($this->regista);

        Livewire::test(RegiaDiretta::class)->assertActionDisabled('pausaTraISet');
    }

    #[Test]
    public function con_spaces_giu_la_regia_puo_ripubblicare_e_il_comando_riprova_da_solo(): void
    {
        $sessione = $this->sessione();
        $this->regia()->ripubblica($sessione);
        $this->actingAs($this->regista);

        $guasto = $this->partialMock(StatoPubblicoDellaDiretta::class);
        $guasto->shouldReceive('pubblica')->andReturn(false);
        $this->app->forgetInstance(RegiaDellaDiretta::class);

        $pagina = Livewire::test(RegiaDiretta::class)
            ->assertActionHidden('ripubblica')
            ->callAction('pausaTraISet')
            ->assertActionVisible('ripubblica')
            ->assertSee('non è arrivato al sito');
        $this->assertTrue($sessione->refresh()->nonPubblicata());
        $this->assertNotSame('pausa', $this->statoScritto($sessione)['stato']);

        // Spaces torna: il comando al minuto pubblica senza che nessuno tocchi.
        $this->app->forgetInstance(StatoPubblicoDellaDiretta::class);
        $this->app->offsetUnset(StatoPubblicoDellaDiretta::class);
        $this->app->forgetInstance(RegiaDellaDiretta::class);
        $this->artisan('diretta:chiudi-le-pause')->assertSuccessful();

        $this->assertFalse($sessione->refresh()->nonPubblicata());
        $this->assertSame('pausa', $this->statoScritto($sessione)['stato']);
        $pagina->call('$refresh')->assertActionHidden('ripubblica');
    }

    #[Test]
    public function spegnere_l_interruttore_toglie_gli_sponsor_anche_a_chi_guarda_gia(): void
    {
        $sessione = $this->sessione();
        $this->regia()->ripubblica($sessione);
        $versione = $this->statoScritto($sessione)['versione'];

        SiteSetting::set('diretta_sponsor_abilitata', '0');
        $this->regia()->ripubblicaLeDiretteDaGiocare();

        $json = $this->statoScritto($sessione);
        $this->assertGreaterThan($versione, $json['versione']);
        $this->assertSame('inattiva', $json['stato']);
        $this->assertFalse($json['ciclo']['attivo']);
        $this->assertSame([], $json['playlist']);
    }

    #[Test]
    public function il_testo_alternativo_viaggia_in_tutte_le_lingue(): void
    {
        $sessione = $this->sessione();
        $sessione->creativitaDellaGara->first()->setTranslations('testo_alternativo', ['it' => 'Estra, energia', 'en' => 'Estra, energy'])->save();

        $this->regia()->ripubblica($sessione);

        $this->assertSame(['it' => 'Estra, energia', 'en' => 'Estra, energy'], $this->statoScritto($sessione)['playlist'][0]['alt']);
    }

    #[Test]
    public function riparti_da_adesso_sposta_l_inizio_del_ciclo(): void
    {
        $sessione = $this->sessione();

        $this->regia()->ripartiDaAdesso($sessione);

        $this->assertSame('2026-10-18T17:40:00+02:00', $this->statoScritto($sessione)['ciclo']['inizio']);
    }

    #[Test]
    public function le_pause_scadute_tornano_in_onda(): void
    {
        $sessione = $this->sessione();
        $this->regia()->pausaTraISet($sessione);

        $this->travel(2)->minutes();
        $this->artisan('diretta:chiudi-le-pause')->assertSuccessful();
        $this->assertSame(StatoDellaDiretta::Pausa, $sessione->refresh()->stato);

        $this->travel(2)->minutes();
        $this->artisan('diretta:chiudi-le-pause')->assertSuccessful();
        $this->assertSame(StatoDellaDiretta::InOnda, $sessione->refresh()->stato);
        $this->assertSame('in_onda', $this->statoScritto($sessione)['stato']);
    }

    #[Test]
    public function dal_telefono_la_regia_manda_lo_spot_senza_conferme_e_resta_nel_registro(): void
    {
        $sessione = $this->sessione();
        $this->actingAs($this->regista);

        Livewire::test(RegiaDiretta::class)
            ->assertSet('gara', $sessione->game_id)
            ->callAction('pausaTraISet')
            ->assertNotified();

        $this->assertSame(StatoDellaDiretta::Pausa, $sessione->refresh()->stato);
        $this->assertDatabaseHas('activity_logs', ['model_type' => LiveSession::class, 'model_id' => $sessione->id, 'user_id' => $this->regista->id]);
    }

    #[Test]
    public function la_regia_mostra_solo_i_pulsanti_che_servono_in_quel_momento(): void
    {
        $sessione = $this->sessione();
        $this->actingAs($this->regista);

        Livewire::test(RegiaDiretta::class)
            ->assertActionVisible('pausaTraISet')
            ->assertActionHidden('finePausa')
            ->assertActionHidden('riaccendi')
            ->assertActionVisible('spegniTutto')
            ->callAction('pausaTraISet')
            ->assertActionVisible('finePausa');

        $this->regia()->spegniTutto($sessione);

        Livewire::test(RegiaDiretta::class)
            ->assertActionVisible('riaccendi')
            ->assertActionHidden('spegniTutto')
            ->assertActionHidden('sospendiCiclo')
            ->assertActionHidden('riprendiCiclo');
    }

    #[Test]
    public function spegni_tutto_chiede_conferma(): void
    {
        $sessione = $this->sessione();
        $this->actingAs($this->regista);

        $pagina = Livewire::test(RegiaDiretta::class)->mountAction('spegniTutto');

        $pagina->assertActionMounted('spegniTutto');
        $this->assertSame(StatoDellaDiretta::InOnda, $sessione->refresh()->stato);

        $pagina->callMountedAction();
        $this->assertSame(StatoDellaDiretta::Inattiva, $sessione->refresh()->stato);
    }

    #[Test]
    public function la_regia_non_si_apre_a_chi_non_cura_gli_sponsor_e_a_interruttore_spento_lo_dice(): void
    {
        $this->sessione();
        $negozio = User::factory()->create();
        $negozio->forceFill(['role' => UserRole::ShopManager, 'is_active' => true])->save();

        $this->actingAs($negozio)->get('/admin/regia-diretta')->assertForbidden();

        SiteSetting::set('diretta_sponsor_abilitata', '0');
        $this->actingAs($this->regista)->get('/admin/regia-diretta')
            ->assertOk()
            ->assertSee('Gli sponsor nella diretta sono spenti');
        $this->assertFalse(RegiaDiretta::shouldRegisterNavigation());
    }

    #[Test]
    public function il_sito_riceve_l_indirizzo_dello_stato_solo_se_ci_sono_cornici(): void
    {
        $sessione = $this->sessione();
        $gara = $sessione->game->load(DirettaPerIlSito::RELAZIONI);

        $diretta = DirettaPerIlSito::perLaGara($gara);

        $this->assertStringEndsWith("/diretta/{$gara->id}.json", $diretta['stato']);
        $this->assertStringEndsWith('/api/diretta/ora', $diretta['ora']);
        $this->assertNull($diretta['preroll']);
    }

    #[Test]
    public function l_ora_del_server_risponde_solo_a_interruttore_acceso_e_non_si_mette_in_cache(): void
    {
        $risposta = $this->getJson('/api/diretta/ora')->assertOk();
        $this->assertSame(now()->getTimestampMs(), $risposta->json('ora'));
        $this->assertStringContainsString('no-store', (string) $risposta->headers->get('Cache-Control'));

        SiteSetting::set('diretta_sponsor_abilitata', '0');
        $this->getJson('/api/diretta/ora')->assertNotFound();
    }

    #[Test]
    public function accendere_l_interruttore_ripubblica_anche_le_gare_lontane(): void
    {
        SiteSetting::set('diretta_sponsor_abilitata', '0');
        $sessione = $this->sessione();
        Game::query()->whereKey($sessione->game_id)->update(['match_date' => now()->addDays(40)->setTime(17, 0)]);
        $this->regia()->ripubblica($sessione->refresh());
        $this->assertSame('inattiva', $this->statoScritto($sessione)['stato']);

        SiteSetting::set('diretta_sponsor_abilitata', '1');
        $this->regia()->ripubblicaLeDiretteDaGiocare();

        $this->assertNotSame('inattiva', $this->statoScritto($sessione)['stato']);
    }

    #[Test]
    public function spegnere_o_cancellare_una_cornice_la_toglie_dalle_dirette_pubblicate(): void
    {
        $sessione = $this->sessione();
        [$prima, $seconda] = $sessione->creativitaDellaGara->all();
        $this->regia()->ripubblica($sessione);
        $versione = $this->statoScritto($sessione)['versione'];

        $prima->update(['attiva' => false]);
        $this->regia()->ripubblicaLeDiretteDaGiocare();
        $this->assertSame([$seconda->id], array_column($this->statoScritto($sessione)['playlist'], 'id'));
        // Chi ha già la diretta aperta accetta solo versioni più alte.
        $this->assertGreaterThan($versione, $this->statoScritto($sessione)['versione']);

        $seconda->delete();
        $this->regia()->ripubblicaLeDiretteDaGiocare();
        $this->assertSame([], $this->statoScritto($sessione)['playlist']);
    }

    #[Test]
    public function salvare_o_cancellare_una_creativita_ripubblica_a_risposta_data_una_volta_sola(): void
    {
        Bus::fake();
        $cornice = $this->cornice();

        $cornice->update(['attiva' => false]);
        $cornice->delete();

        Bus::assertDispatchedAfterResponseTimes(RipubblicaLoStatoDelleDirette::class, 1);
    }

    #[Test]
    public function se_la_gara_cambia_orario_il_json_si_riscrive(): void
    {
        $sessione = $this->sessione();
        $this->regia()->ripubblica($sessione);
        $versione = $this->statoScritto($sessione)['versione'];

        $sessione->game->update(['match_date' => now()->setTime(18, 0)]);

        $this->assertSame('2026-10-18T18:05:00+02:00', $this->statoScritto($sessione)['ciclo']['inizio']);
        $this->assertGreaterThan($versione, $this->statoScritto($sessione)['versione']);
    }

    #[Test]
    public function mostra_offre_solo_le_cornici_della_gara(): void
    {
        $sessione = $this->sessione();
        $estranea = $this->cornice();
        $this->actingAs($this->regista);

        Livewire::test(RegiaDiretta::class)
            ->callAction('mostra', ['creativita' => $estranea->id, 'secondi' => 30])
            ->assertHasActionErrors(['creativita']);

        $this->assertSame(StatoDellaDiretta::InOnda, $sessione->refresh()->stato);
    }
}

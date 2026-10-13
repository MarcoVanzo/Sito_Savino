<?php

namespace Tests\Feature\Diretta;

use App\Enums\GameStatus;
use App\Enums\TipoCreativita;
use App\Enums\UserRole;
use App\Enums\UsoCreativita;
use App\Filament\Actions\ImpostaLaDirettaAction;
use App\Filament\Pages\Settings\DirettaSettingsPage;
use App\Models\Game;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Models\SiteSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il pre-roll arriva al sito con la gara (home e calendario) solo con
 * l'interruttore generale acceso; spento, le props sono quelle di sempre.
 */
class PrerollDellaDirettaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        Storage::fake('public');
    }

    private function accendi(bool $acceso = true): void
    {
        SiteSetting::set('diretta_sponsor_abilitata', $acceso ? '1' : '0');
    }

    private function gara(): Game
    {
        return Game::factory()->create([
            'home_team_id' => Team::factory()->create(['is_internal' => true])->id,
            'away_team_id' => Team::factory()->create(['is_internal' => false])->id,
            'match_date' => now()->addDays(2)->setTime(17, 0),
            'status' => GameStatus::Scheduled,
            'stream_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    private function spot(array $attributi = []): LiveCreative
    {
        $spot = LiveCreative::factory()->create(['tipo' => TipoCreativita::Immagine, 'uso' => UsoCreativita::Preroll, ...$attributi]);
        ob_start();
        imagepng(imagecreatetruecolor(1920, 1080));
        $spot->addMedia(UploadedFile::fake()->createWithContent('spot.png', (string) ob_get_clean()))
            ->toMediaCollection(LiveCreative::COLLEZIONE_CONTENUTO);

        return $spot;
    }

    /** La home appena calcolata: la seconda visita arriverebbe dalla cache della pagina. */
    private function nextGame(): ?array
    {
        Cache::flush();

        return $this->get('/')->assertOk()->viewData('page')['props']['nextGame'] ?? null;
    }

    #[Test]
    public function con_l_interruttore_spento_la_gara_non_porta_sponsor(): void
    {
        $gara = $this->gara();
        LiveSession::perLaGara($gara)->ordina('creativitaDelPreroll', [$this->spot()->id]);

        $this->assertNull($this->nextGame()['diretta_sponsor']);
    }

    #[Test]
    public function acceso_il_pre_roll_arriva_con_la_prossima_gara(): void
    {
        $this->accendi();
        $gara = $this->gara();
        $spot = $this->spot(['link_url' => 'https://www.estra.it/']);
        LiveSession::perLaGara($gara)->ordina('creativitaDelPreroll', [$spot->id]);

        $prossima = $this->nextGame();
        $diretta = $prossima['diretta_sponsor'];

        $this->assertSame($gara->id, $diretta['gara']);
        $this->assertSame(5, $diretta['preroll']['salta_dopo']);
        $this->assertSame([$spot->id], array_column($diretta['preroll']['creativita'], 'id'));
        $this->assertSame('https://www.estra.it/', $diretta['preroll']['creativita'][0]['link']);
        $this->assertArrayNotHasKey('live_session', $prossima);
    }

    #[Test]
    public function spenti_scaduti_e_link_non_web_restano_fuori(): void
    {
        $this->accendi();
        $gara = $this->gara();
        $buono = $this->spot(['link_url' => 'javascript:alert(1)']);
        $scaduto = $this->spot(['valida_al' => now()->subDay()]);
        $spento = $this->spot(['attiva' => false]);
        LiveSession::perLaGara($gara)->ordina('creativitaDelPreroll', [$buono->id, $scaduto->id, $spento->id]);

        $creativita = $this->nextGame()['diretta_sponsor']['preroll']['creativita'];

        $this->assertSame([$buono->id], array_column($creativita, 'id'));
        $this->assertNull($creativita[0]['link']);
    }

    #[Test]
    public function senza_spot_validi_la_diretta_resta_quella_di_sempre(): void
    {
        $this->accendi();
        LiveSession::perLaGara($this->gara());

        $this->assertNull($this->nextGame()['diretta_sponsor']);
    }

    #[Test]
    public function la_diretta_nasce_con_i_valori_di_partenza_e_gli_orari_della_gara(): void
    {
        SiteSetting::set('diretta_ciclo_ogni_minuti', '12');
        $gara = $this->gara();

        $sessione = LiveSession::perLaGara($gara);

        $this->assertSame(12, $sessione->ciclo_ogni_minuti);
        $this->assertSame('17:05', $sessione->ciclo_inizio->format('H:i'));
        $this->assertSame('20:00', $sessione->ciclo_fine->format('H:i'));
        $this->assertSame($sessione->id, LiveSession::perLaGara($gara)->id);
    }

    private function comunicazione(): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::CommunicationManager, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        return $utente;
    }

    #[Test]
    public function dalla_pagina_diretta_si_mettono_link_sponsor_e_grafiche(): void
    {
        $this->accendi();
        $utente = $this->comunicazione();
        $gara = $this->gara();
        $gara->update(['stream_url' => null]);
        $primo = $this->spot();
        $secondo = $this->spot(['sponsor_id' => $primo->sponsor_id]);
        $cornice = $this->spot(['sponsor_id' => $primo->sponsor_id, 'uso' => UsoCreativita::Cornice]);
        $altroSponsor = $this->spot();

        Livewire::test(DirettaSettingsPage::class)
            ->assertCanSeeTableRecords([$gara])
            ->callTableAction('imposta', $gara, [
                'stream_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'preroll_attivo' => true,
                'preroll_salta_dopo_secondi' => 3,
                // Lo stato del Repeater come lo idrata il modulo: chiavi interne.
                'sponsor' => [
                    'a' => ['sponsor_id' => $altroSponsor->sponsor_id, 'preroll' => [$altroSponsor->id], 'cornici' => []],
                    // Una grafica di un altro sponsor spuntata per errore non passa.
                    'b' => ['sponsor_id' => $primo->sponsor_id, 'preroll' => [$secondo->id, $primo->id, $altroSponsor->id], 'cornici' => [$cornice->id]],
                ],
                'ciclo_attivo' => true,
                'ciclo_ogni_minuti' => 8,
                'ciclo_durata_secondi' => 20,
                'ciclo_ritardo_minuti' => 10,
                'pausa_durata_predefinita_secondi' => 120,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $gara->refresh()->stream_url);
        $sessione = $gara->liveSession()->firstOrFail();
        $this->assertSame([$altroSponsor->id, $secondo->id, $primo->id], $sessione->creativitaDelPreroll()->pluck('live_creatives.id')->all());
        $this->assertSame([$cornice->id], $sessione->creativitaDellaGara()->pluck('live_creatives.id')->all());
        $this->assertSame(3, $sessione->preroll_salta_dopo_secondi);
        $this->assertSame('17:10', $sessione->ciclo_inizio->format('H:i'));
        $this->assertDatabaseHas('activity_logs', ['model_type' => LiveSession::class, 'model_id' => $sessione->id, 'user_id' => $utente->id]);
    }

    #[Test]
    public function riaprendo_si_ritrovano_gli_sponsor_con_le_loro_grafiche(): void
    {
        $this->comunicazione();
        $gara = $this->gara();
        $spot = $this->spot();
        $cornice = $this->spot(['sponsor_id' => $spot->sponsor_id, 'uso' => UsoCreativita::Cornice]);
        $sessione = LiveSession::perLaGara($gara);
        $sessione->ordina('creativitaDelPreroll', [$spot->id]);
        $sessione->ordina('creativitaDellaGara', [$cornice->id]);

        $pagina = Livewire::test(DirettaSettingsPage::class)->mountTableAction('imposta', $gara);

        $dati = $pagina->get('mountedTableActionsData.0');
        $this->assertSame($gara->stream_url, $dati['stream_url']);
        $voci = array_map(fn (array $v): array => array_intersect_key($v, array_flip(['sponsor_id', 'preroll', 'cornici'])), array_values($dati['sponsor']));
        $this->assertSame([['sponsor_id' => $spot->sponsor_id, 'preroll' => [$spot->id], 'cornici' => [$cornice->id]]], $voci);
    }

    #[Test]
    public function il_link_si_mette_anche_a_interruttore_spento(): void
    {
        $this->comunicazione();
        $gara = $this->gara();

        Livewire::test(DirettaSettingsPage::class)
            ->callTableAction('imposta', $gara, [
                'stream_url' => 'https://www.youtube.com/watch?v=nuovoLink1',
                'preroll_attivo' => false, 'preroll_salta_dopo_secondi' => 5, 'sponsor' => [],
                'ciclo_attivo' => true, 'ciclo_ogni_minuti' => 10, 'ciclo_durata_secondi' => 15,
                'ciclo_ritardo_minuti' => 5, 'pausa_durata_predefinita_secondi' => 180,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('https://www.youtube.com/watch?v=nuovoLink1', $gara->refresh()->stream_url);
    }

    #[Test]
    public function un_link_non_web_si_rifiuta(): void
    {
        $this->comunicazione();
        $gara = $this->gara();

        Livewire::test(DirettaSettingsPage::class)
            ->callTableAction('imposta', $gara, ['stream_url' => 'javascript:alert(1)'])
            ->assertHasTableActionErrors(['stream_url']);
    }

    #[Test]
    public function l_area_sportiva_non_apre_la_pagina_diretta(): void
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SportCoordinator, 'is_active' => true])->save();

        $this->actingAs($utente->refresh())->get('/admin/settings/diretta')->assertForbidden();
    }

    #[Test]
    public function salvare_la_diretta_svuota_la_cache_della_home(): void
    {
        $gara = $this->gara();
        $this->accendi();
        $this->assertNull($this->nextGame()['diretta_sponsor']);

        $spot = $this->spot();
        ImpostaLaDirettaAction::salva($gara, [
            'preroll_attivo' => true,
            'sponsor' => [['sponsor_id' => $spot->sponsor_id, 'preroll' => [$spot->id], 'cornici' => []]],
            'preroll_salta_dopo_secondi' => 5,
            'ciclo_attivo' => true,
            'ciclo_ogni_minuti' => 10,
            'ciclo_durata_secondi' => 15,
            'ciclo_ritardo_minuti' => 5,
            'pausa_durata_predefinita_secondi' => 180,
        ]);

        $this->assertNotNull($this->nextGame()['diretta_sponsor']);
    }

    #[Test]
    public function aprire_la_finestra_senza_salvare_non_crea_la_diretta(): void
    {
        $this->accendi();
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::CommunicationManager, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        Livewire::test(DirettaSettingsPage::class)
            ->mountTableAction('imposta', $this->gara())
            ->assertTableActionDataSet(['ciclo_ogni_minuti' => 10, 'sponsor' => []]);

        $this->assertSame(0, LiveSession::count());
        $this->assertDatabaseMissing('activity_logs', ['model_type' => LiveSession::class]);
    }

    #[Test]
    public function risalvare_non_annulla_riparti_da_adesso_ma_un_nuovo_ritardo_si(): void
    {
        $gara = $this->gara();
        $sessione = LiveSession::perLaGara($gara);
        $sessione->update(['ciclo_inizio' => $gara->match_date->copy()->addMinutes(42)]);
        $dati = [
            'preroll_attivo' => false, 'preroll_salta_dopo_secondi' => 5, 'ciclo_attivo' => true,
            'ciclo_ogni_minuti' => 10, 'ciclo_durata_secondi' => 15, 'ciclo_ritardo_minuti' => 5,
            'pausa_durata_predefinita_secondi' => 180,
        ];

        ImpostaLaDirettaAction::salva($gara, $dati);
        $this->assertSame('17:42', $sessione->refresh()->ciclo_inizio->format('H:i'));

        ImpostaLaDirettaAction::salva($gara, [...$dati, 'ciclo_ritardo_minuti' => 15]);
        $this->assertSame('17:15', $sessione->refresh()->ciclo_inizio->format('H:i'));
    }

    #[Test]
    public function se_la_gara_cambia_orario_il_ciclo_la_segue(): void
    {
        $gara = $this->gara();
        $sessione = LiveSession::perLaGara($gara);

        $gara->update(['match_date' => $gara->match_date->copy()->setTime(20, 30)]);

        $sessione->refresh();
        $this->assertSame('20:35', $sessione->ciclo_inizio->format('H:i'));
        $this->assertSame('23:30', $sessione->ciclo_fine->format('H:i'));
        $this->assertSame(2, $sessione->versione);
    }

    #[Test]
    public function le_azioni_sulla_diretta_non_svuotano_le_cache_delle_pagine(): void
    {
        $sessione = LiveSession::perLaGara($this->gara());
        Cache::put('public:home:it', 'in cache', 600);

        $sessione->update(['ciclo_attivo' => false]);

        $this->assertSame('in cache', Cache::get('public:home:it'));
    }

    #[Test]
    public function in_imposta_compare_l_anteprima_delle_grafiche_spuntate(): void
    {
        $this->comunicazione();
        $gara = $this->gara();
        $cornice = $this->spot(['uso' => UsoCreativita::Cornice, 'nome' => 'Cornice autunno']);
        $sessione = LiveSession::perLaGara($gara);
        $sessione->ordina('creativitaDellaGara', [$cornice->id]);

        Livewire::test(DirettaSettingsPage::class)
            ->mountTableAction('imposta', $gara)
            ->assertSee('Cornice autunno')
            ->assertSee('adt-colonna', false);
    }
}

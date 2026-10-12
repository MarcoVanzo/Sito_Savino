<?php

namespace Tests\Feature\Diretta;

use App\Enums\GameStatus;
use App\Enums\TipoCreativita;
use App\Enums\UserRole;
use App\Enums\UsoCreativita;
use App\Filament\Actions\DirettaESponsorAction;
use App\Filament\Resources\GameResource\Pages\ViewGame;
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

    #[Test]
    public function la_comunicazione_imposta_la_diretta_dalla_scheda_della_gara(): void
    {
        $this->accendi();
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::CommunicationManager, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());
        $gara = $this->gara();
        [$primo, $secondo] = [$this->spot(), $this->spot()];

        Livewire::test(ViewGame::class, ['record' => $gara->getRouteKey()])
            ->assertActionVisible('direttaESponsor')
            ->callAction('direttaESponsor', [
                'preroll_attivo' => true,
                // Lo stato del Repeater semplice, come lo idrata il modulo.
                'preroll' => ['a' => ['id' => $secondo->id], 'b' => ['id' => $primo->id]],
                'preroll_salta_dopo_secondi' => 3,
                'gara' => [],
                'ciclo_attivo' => true,
                'ciclo_ogni_minuti' => 8,
                'ciclo_durata_secondi' => 20,
                'ciclo_ritardo_minuti' => 10,
                'pausa_durata_predefinita_secondi' => 120,
            ])
            ->assertHasNoActionErrors();

        $sessione = $gara->liveSession()->firstOrFail();
        $this->assertSame([$secondo->id, $primo->id], $sessione->creativitaDelPreroll()->pluck('live_creatives.id')->all());
        $this->assertSame(3, $sessione->preroll_salta_dopo_secondi);
        $this->assertSame('17:10', $sessione->ciclo_inizio->format('H:i'));
        $this->assertSame(2, $sessione->versione);
        $this->assertDatabaseHas('activity_logs', ['model_type' => LiveSession::class, 'model_id' => $sessione->id, 'user_id' => $utente->id]);
    }

    #[Test]
    public function con_l_interruttore_spento_l_azione_non_compare(): void
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::CommunicationManager, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        Livewire::test(ViewGame::class, ['record' => $this->gara()->getRouteKey()])
            ->assertActionHidden('direttaESponsor');
    }

    #[Test]
    public function l_area_sportiva_non_governa_gli_sponsor_della_diretta(): void
    {
        $this->accendi();
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SportCoordinator, 'is_active' => true])->save();
        $this->actingAs($utente->refresh());

        Livewire::test(ViewGame::class, ['record' => $this->gara()->getRouteKey()])
            ->assertActionHidden('direttaESponsor');
    }

    #[Test]
    public function salvare_la_diretta_svuota_la_cache_della_home(): void
    {
        $gara = $this->gara();
        $this->accendi();
        $this->assertNull($this->nextGame()['diretta_sponsor']);

        DirettaESponsorAction::salva($gara, [
            'preroll_attivo' => true,
            'preroll' => [$this->spot()->id],
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

        Livewire::test(ViewGame::class, ['record' => $this->gara()->getRouteKey()])
            ->mountAction('direttaESponsor')
            ->assertActionDataSet(['ciclo_ogni_minuti' => 10, 'preroll' => []]);

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

        DirettaESponsorAction::salva($gara, $dati);
        $this->assertSame('17:42', $sessione->refresh()->ciclo_inizio->format('H:i'));

        DirettaESponsorAction::salva($gara, [...$dati, 'ciclo_ritardo_minuti' => 15]);
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
}

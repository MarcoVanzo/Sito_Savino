<?php

namespace Tests\Feature\Diretta;

use App\Enums\GameStatus;
use App\Enums\TipoCreativita;
use App\Enums\UserRole;
use App\Enums\UsoCreativita;
use App\Models\Game;
use App\Models\LiveAdStat;
use App\Models\LiveCreative;
use App\Models\LiveSession;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La prova della diretta per lo staff: la vede solo chi cura gli sponsor,
 * anche a interruttore spento, senza contatori e con la cornice ogni minuto.
 */
class ProvaDellaDirettaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    private function utente(UserRole $ruolo): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => $ruolo, 'is_active' => true])->save();

        return $utente->refresh();
    }

    private function grafica(UsoCreativita $uso): LiveCreative
    {
        $grafica = LiveCreative::factory()->create(['uso' => $uso, 'tipo' => TipoCreativita::Immagine]);
        ob_start();
        imagepng(imagecreatetruecolor(1920, 1080));
        $grafica->addMedia(UploadedFile::fake()->createWithContent('g.png', (string) ob_get_clean()))->toMediaCollection(LiveCreative::COLLEZIONE_CONTENUTO);

        return $grafica;
    }

    private function gara(): Game
    {
        $gara = Game::factory()->create([
            'home_team_id' => Team::factory()->create(['is_internal' => true])->id,
            'away_team_id' => Team::factory()->create(['is_internal' => false])->id,
            'match_date' => now()->addDays(3),
            'status' => GameStatus::Scheduled,
            'stream_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $sessione = LiveSession::perLaGara($gara);
        $sessione->ordina('creativitaDelPreroll', [$this->grafica(UsoCreativita::Preroll)->id]);
        $sessione->ordina('creativitaDellaGara', [$this->grafica(UsoCreativita::Cornice)->id]);

        return $gara;
    }

    #[Test]
    public function chi_cura_gli_sponsor_prova_la_diretta_anche_a_interruttore_spento(): void
    {
        $gara = $this->gara();

        $risposta = $this->actingAs($this->utente(UserRole::CommunicationManager))
            ->get(route('diretta.prova', $gara))
            ->assertOk();

        $risposta->assertInertia(fn (Assert $pagina) => $pagina
            ->component('Public/ProvaDiretta')
            ->where('interruttoreAcceso', false)
            ->where('gara.embedUrl', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->where('sponsor.prova', true)
            ->where('sponsor.eventi', null)
            ->where('sponsor.stato', null)
            ->where('sponsor.stato_prova.ciclo.ogni_minuti', 1)
            ->has('sponsor.preroll.creativita', 1)
            ->has('sponsor.stato_prova.playlist', 1));

        $this->assertStringContainsString('no-store', (string) $risposta->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $risposta->headers->get('X-Robots-Tag'));
        $this->assertSame(0, LiveAdStat::count());
    }

    #[Test]
    public function i_tifosi_e_gli_altri_ruoli_non_la_aprono(): void
    {
        $gara = $this->gara();

        $this->get(route('diretta.prova', $gara))->assertRedirect();
        $this->actingAs($this->utente(UserRole::ShopManager))->get(route('diretta.prova', $gara))->assertForbidden();
        $this->actingAs($this->utente(UserRole::Customer))->get(route('diretta.prova', $gara))->assertForbidden();
    }

    #[Test]
    public function una_partita_senza_sponsor_si_prova_senza_sponsor(): void
    {
        $gara = Game::factory()->create(['stream_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->actingAs($this->utente(UserRole::CommunicationManager))
            ->get(route('diretta.prova', $gara))
            ->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina->where('sponsor', null));
    }
}
